<?php

namespace GiveDataGenerator\DataGenerator;

use DateTime;
use Exception;
use Give\Campaigns\Actions\CacheCampaignData;
use Give\Campaigns\Models\Campaign;
use Give\Donations\ValueObjects\DonationMetaKeys;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Database\DB;

/**
 * Generates donations in bulk through the DonationGenerator, with the per-donation side effects
 * that do not scale (email, one Action Scheduler job per donation, a donor recount per donation)
 * held back and replaced by one pass at the end. Every donation and donor created here is tagged
 * with GENERATED_META_KEY so reset() can remove them without touching real data.
 *
 * @since 1.1.0
 */
class BulkDonationSeeder
{
    public const GENERATED_META_KEY = '_give_data_generator';

    private const BATCH_SIZE = 1000;
    private const YEARS_OF_HISTORY = 3;

    /** @var DonationGenerator */
    private $donationGenerator;

    /** @var CampaignGenerator */
    private $campaignGenerator;

    public function __construct(DonationGenerator $donationGenerator, CampaignGenerator $campaignGenerator)
    {
        $this->donationGenerator = $donationGenerator;
        $this->campaignGenerator = $campaignGenerator;
    }

    /**
     * Make sure at least $count campaigns with default forms exist and return them.
     *
     * @since 1.1.0
     *
     * @return Campaign[]
     */
    public function ensureCampaigns(int $count): array
    {
        $campaigns = $this->campaignsWithForms();
        $missing = $count - count($campaigns);

        if ($missing > 0) {
            $this->campaignGenerator->generateCampaigns($missing, 'active', 'amount', 1000, 100000, 'random', true, false, '90_days', true, 'Load Test Campaign');
            $campaigns = $this->campaignsWithForms();
        }

        return array_slice($campaigns, 0, max($count, 1));
    }

    /**
     * Create $count donations across $campaigns, dated over the last three years. New donors are
     * created until the site holds $donorTarget donors, then existing ones are reused.
     *
     * @since 1.1.0
     *
     * @param Campaign[] $campaigns
     * @param callable|null $onBatch Receives the running total created so far after every batch.
     *
     * @return int Donations created.
     * @throws Exception
     */
    public function seed(array $campaigns, int $count, int $donorTarget, string $mode = 'test', string $status = 'random', ?callable $onBatch = null): int
    {
        $end = new DateTime();
        $start = (clone $end)->modify('-' . self::YEARS_OF_HISTORY . ' years');
        $created = 0;

        $this->quietly(function () use ($campaigns, $count, $donorTarget, $mode, $status, $start, $end, $onBatch, &$created) {
            $this->donationGenerator->deferDonorStats = true;

            while ($created < $count) {
                $lastDonationId = $this->maxDonationId();
                $lastDonorId = $this->maxDonorId();
                $batch = min(self::BATCH_SIZE, $count - $created);

                // New donors only until the donor target is met, then reuse existing ones.
                $donors = $this->countDonors();
                $withNewDonors = min($batch, $donors > 0 ? max(0, $donorTarget - $donors) : max(1, $donorTarget));

                foreach (array_filter(['create_new' => $withNewDonors, 'use_existing' => $batch - $withNewDonors]) as $donorMethod => $portion) {
                    foreach ($this->split($portion, count($campaigns)) as $index => $perCampaign) {
                        if ($perCampaign > 0) {
                            $this->donationGenerator->generateDonations($campaigns[$index], $perCampaign, 'custom', $mode, $status, $donorMethod, 0, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
                        }
                    }
                }

                $created += $this->tagDonationsAfter($lastDonationId);
                $this->tagDonorsAfter($lastDonorId);

                if ($onBatch) {
                    $onBatch($created);
                }
            }

            $this->donationGenerator->deferDonorStats = false;
        });

        $this->recount($campaigns);

        return $created;
    }

    /**
     * Remove every donation and donor this seeder created, and nothing else.
     *
     * @since 1.1.0
     *
     * @return array{donations:int, donors:int}
     */
    public function reset(): array
    {
        global $wpdb;

        $meta = $wpdb->prefix . 'give_donationmeta';
        $donorMeta = $wpdb->prefix . 'give_donormeta';
        $tag = self::GENERATED_META_KEY;

        $donationIds = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT donation_id FROM {$meta} WHERE meta_key = %s", $tag)));
        $donorIds = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT donor_id FROM {$donorMeta} WHERE meta_key = %s", $tag)));

        foreach (array_chunk($donationIds, 5000) as $chunk) {
            $in = implode(',', $chunk);
            DB::query("DELETE FROM {$wpdb->prefix}give_revenue WHERE donation_id IN ({$in})");
            DB::query("DELETE FROM {$wpdb->prefix}give_comments WHERE comment_type = 'donor_donation' AND comment_parent IN ({$in})");
            DB::query("DELETE FROM {$wpdb->prefix}give_sequential_ordering WHERE payment_id IN ({$in})");
            DB::query("DELETE FROM {$meta} WHERE donation_id IN ({$in})");
            DB::query("DELETE FROM {$wpdb->posts} WHERE ID IN ({$in})");
        }

        foreach (array_chunk($donorIds, 5000) as $chunk) {
            $in = implode(',', $chunk);
            DB::query("DELETE FROM {$donorMeta} WHERE donor_id IN ({$in})");
            DB::query("DELETE FROM {$wpdb->prefix}give_donors WHERE id IN ({$in})");
        }

        delete_option('give_campaigns_data');
        delete_option('give_campaigns_subscriptions_data');

        return ['donations' => count($donationIds), 'donors' => count($donorIds)];
    }

    public function countDonations(): int
    {
        global $wpdb;
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'give_payment'");
    }

    public function countDonors(): int
    {
        global $wpdb;
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}give_donors");
    }

    public function countGenerated(): int
    {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}give_donationmeta WHERE meta_key = %s", self::GENERATED_META_KEY));
    }

    /**
     * Rebuild what the deferred per-donation work would have maintained: the legacy donor totals
     * (one aggregate query instead of one per donation, which scans the meta table on large sites)
     * and the campaign caches (once per campaign instead of one queued job per donation).
     *
     * @param Campaign[] $campaigns
     */
    private function recount(array $campaigns): void
    {
        global $wpdb;

        $meta = $wpdb->prefix . 'give_donationmeta';

        DB::query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}give_donors d
             LEFT JOIN (
                 SELECT dm.meta_value + 0 AS donor_id, COUNT(*) AS purchase_count,
                        SUM(IFNULL(am.meta_value, 0) - IFNULL(fm.meta_value, 0)) AS purchase_value
                 FROM {$wpdb->posts} p
                 INNER JOIN {$meta} dm ON dm.donation_id = p.ID AND dm.meta_key = %s
                 LEFT JOIN {$meta} am ON am.donation_id = p.ID AND am.meta_key = %s
                 LEFT JOIN {$meta} fm ON fm.donation_id = p.ID AND fm.meta_key = %s
                 WHERE p.post_type = 'give_payment' AND p.post_status IN (%s, %s)
                 GROUP BY dm.meta_value
             ) t ON t.donor_id = d.id
             SET d.purchase_count = IFNULL(t.purchase_count, 0), d.purchase_value = IFNULL(t.purchase_value, 0)",
            DonationMetaKeys::DONOR_ID,
            DonationMetaKeys::AMOUNT,
            DonationMetaKeys::FEE_AMOUNT_RECOVERED,
            DonationStatus::COMPLETE,
            DonationStatus::RENEWAL
        ));

        foreach ($campaigns as $campaign) {
            give(CacheCampaignData::class)->handleCache($campaign->id);
        }
    }

    /**
     * Run $callback with outgoing email and the per-donation campaign cache job switched off, using
     * core's own hook-disabling filters. Everything that writes data stays on.
     */
    private function quietly(callable $callback): void
    {
        $disabled = [
            'give_disable_hook-give_insert_payment:' . CacheCampaignData::class . '@__invoke',
            'give_disable_hook-give_update_payment_status:' . CacheCampaignData::class . '@__invoke',
        ];

        add_filter('pre_wp_mail', '__return_false');
        foreach ($disabled as $filter) {
            add_filter($filter, '__return_true');
        }

        try {
            $callback();
        } finally {
            remove_filter('pre_wp_mail', '__return_false');
            foreach ($disabled as $filter) {
                remove_filter($filter, '__return_true');
            }
        }
    }

    /**
     * @return Campaign[]
     */
    private function campaignsWithForms(): array
    {
        return array_values(array_filter(Campaign::query()->getAll() ?: [], static function (Campaign $campaign) {
            return (bool)$campaign->defaultForm();
        }));
    }

    private function tagDonationsAfter(int $lastId): int
    {
        global $wpdb;
        return (int)DB::query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}give_donationmeta (donation_id, meta_key, meta_value)
             SELECT ID, %s, '1' FROM {$wpdb->posts} WHERE post_type = 'give_payment' AND ID > %d",
            self::GENERATED_META_KEY,
            $lastId
        ));
    }

    private function tagDonorsAfter(int $lastId): void
    {
        global $wpdb;
        DB::query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}give_donormeta (donor_id, meta_key, meta_value)
             SELECT id, %s, '1' FROM {$wpdb->prefix}give_donors WHERE id > %d",
            self::GENERATED_META_KEY,
            $lastId
        ));
    }

    private function maxDonationId(): int
    {
        global $wpdb;
        return (int)$wpdb->get_var("SELECT IFNULL(MAX(ID), 0) FROM {$wpdb->posts} WHERE post_type = 'give_payment'");
    }

    private function maxDonorId(): int
    {
        global $wpdb;
        return (int)$wpdb->get_var("SELECT IFNULL(MAX(id), 0) FROM {$wpdb->prefix}give_donors");
    }

    /**
     * Split $total into $parts near-equal integers.
     *
     * @return int[]
     */
    private function split(int $total, int $parts): array
    {
        $parts = max(1, $parts);
        $out = array_fill(0, $parts, intdiv($total, $parts));
        for ($i = 0; $i < $total % $parts; $i++) {
            $out[$i]++;
        }

        return $out;
    }
}
