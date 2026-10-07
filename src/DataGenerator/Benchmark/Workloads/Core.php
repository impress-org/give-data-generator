<?php

namespace GiveDataGenerator\DataGenerator\Benchmark\Workloads;

use Give\Campaigns\CampaignDonationQuery;
use Give\Campaigns\CampaignsDataQuery;
use Give\DonationForms\AsyncData\AsyncDataHelpers;
use Give\Donations\Models\Donation;
use Give\Donations\ValueObjects\DonationMode;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Donations\ValueObjects\DonationType;
use Give\Donors\DonorStatisticsQuery;
use Give\Framework\Support\ValueObjects\Money;
use Give\PaymentGateways\Gateways\TestGateway\TestGateway;
use Give_Payment_Stats;
use GiveDataGenerator\DataGenerator\Benchmark\Benchmark;

/**
 * GiveWP core's workloads: the admin list screens, the v3 REST API, campaign and form totals,
 * reports and statistics, and a donation write.
 *
 * @since 1.2.0
 */
class Core
{
    /**
     * @since 1.2.0
     */
    public function register(Benchmark $bench): void
    {
        $this->adminScreens($bench);
        $this->restApi($bench);
        $this->totals($bench);
        $this->reports($bench);
        $this->writes($bench);
    }

    /**
     * The Donations and Donors list tables call these v2 admin endpoints.
     */
    private function adminScreens(Benchmark $bench): void
    {
        $bench->measure('donations_screen_page1', static function () use ($bench) {
            $bench->rest('/give-api/v2/admin/donations', ['page' => 1, 'perPage' => 30]);
        });
        $bench->measure('donations_screen_lastpage', static function () use ($bench) {
            $bench->rest('/give-api/v2/admin/donations', ['page' => $bench->lastPage, 'perPage' => 30]);
        });
        $bench->measure('donations_screen_search_email', static function () use ($bench) {
            $bench->rest('/give-api/v2/admin/donations', ['page' => 1, 'perPage' => 30, 'search' => $bench->donor->email]);
        });
        $bench->measure('donations_screen_stats', static function () use ($bench) {
            $bench->rest('/give-api/v2/admin/donations/stats');
        });
        $bench->measure('donors_screen_page1', static function () use ($bench) {
            $bench->rest('/give-api/v2/admin/donors', ['page' => 1, 'perPage' => 30]);
        });
        $bench->measure('donors_screen_search_email', static function () use ($bench) {
            $bench->rest('/give-api/v2/admin/donors', ['page' => 1, 'perPage' => 30, 'search' => $bench->donor->email]);
        });
    }

    /**
     * The public v3 REST API, used by the newer admin apps and by integrations. Lists are read in
     * the site's current mode, as the admin screens are; the route's own default is live.
     */
    private function restApi(Benchmark $bench): void
    {
        $mode = give_is_test_mode() ? 'test' : 'live';

        $bench->measure('donations_api_v3_page1', static function () use ($bench, $mode) {
            $bench->rest('/givewp/v3/donations', ['page' => 1, 'per_page' => 30, 'mode' => $mode]);
        });
        $bench->measure('donors_api_v3_page1', static function () use ($bench, $mode) {
            $bench->rest('/givewp/v3/donors', ['page' => 1, 'per_page' => 30, 'onlyWithDonations' => true, 'mode' => $mode]);
        });
        $bench->measure('donor_api_v3_statistics', static function () use ($bench) {
            $bench->rest('/givewp/v3/donors/' . $bench->donor->id . '/statistics');
        });
    }

    /**
     * Campaign and form totals, with every cache cleared first.
     */
    private function totals(Benchmark $bench): void
    {
        $clearCaches = [$bench, 'clearCaches'];

        $bench->measure('campaigns_data_all_uncached', static function () use ($bench) {
            $donations = CampaignsDataQuery::donations($bench->campaignIds);
            $donations->collectIntendedAmounts();
            $donations->collectDonations();
            $donations->collectDonors();
            $subscriptions = CampaignsDataQuery::subscriptions($bench->campaignIds);
            $subscriptions->collectInitialAmounts();
            $subscriptions->collectDonations();
            $subscriptions->collectDonors();
        }, $clearCaches);
        $bench->measure('campaign_grid_12_uncached', static function () use ($bench) {
            foreach (array_slice($bench->campaigns, 0, 12) as $campaign) {
                $query = new CampaignDonationQuery($campaign);
                $query->sumIntendedAmount();
                $query->countDonations();
                $query->countDonors();
            }
        }, $clearCaches);
        $bench->measure('campaign_sum_intended', static function () use ($bench) {
            (new CampaignDonationQuery($bench->campaign))->sumIntendedAmount();
        });
        $bench->measure('campaign_by_day_1y', static function () use ($bench) {
            (new CampaignDonationQuery($bench->campaign))->between($bench->yearAgo, $bench->now)->getDonationsByDate('DAY');
        });
        $bench->measure('forms_list_20_uncached', static function () use ($bench) {
            foreach ($bench->formIds as $formId) {
                give_goal_progress_stats($formId);
                AsyncDataHelpers::getFormDonationsCountValue($formId);
                AsyncDataHelpers::getFormRevenueValue($formId);
            }
        }, $clearCaches);
    }

    /**
     * Reports and statistics.
     */
    private function reports(Benchmark $bench): void
    {
        $clearCaches = [$bench, 'clearCaches'];

        // The Reports screen opens on the past week; that is the request every visit makes.
        $bench->measure('reports_income_7d_uncached', static function () use ($bench) {
            $bench->rest('/give-api/v2/reports/income', [
                'start' => $bench->weekAgo->format('Y-m-d'),
                'end' => $bench->now->format('Y-m-d'),
                'currency' => give_get_currency(),
                'testMode' => give_is_test_mode(),
            ]);
        }, $clearCaches);
        $bench->measure('legacy_stats_earnings_1y_uncached', static function () use ($bench) {
            (new Give_Payment_Stats())->get_earnings(0, $bench->yearAgo->getTimestamp(), $bench->now->getTimestamp());
        }, $clearCaches);
        $bench->measure('donor_statistics_query', static function () use ($bench) {
            $query = new DonorStatisticsQuery($bench->donor);
            $query->getLifetimeDonationsAmount();
            $query->getAverageDonationAmount();
            $query->getDonationsCount();
        });
    }

    /**
     * Writes. Every donation created here is deleted again so the dataset keeps its size.
     */
    private function writes(Benchmark $bench): void
    {
        global $wpdb;

        $created = [];
        $newDonation = static function () use ($bench, &$created): Donation {
            $form = $bench->campaign->defaultForm();
            $donation = Donation::create([
                'status' => DonationStatus::COMPLETE(),
                'gatewayId' => TestGateway::id(),
                'mode' => DonationMode::TEST(),
                'type' => DonationType::SINGLE(),
                'amount' => new Money(2500, give_get_currency()),
                'donorId' => $bench->donor->id,
                'firstName' => $bench->donor->firstName,
                'lastName' => $bench->donor->lastName,
                'email' => $bench->donor->email,
                'campaignId' => $bench->campaign->id,
                'formId' => $form ? $form->id : 0,
                'formTitle' => $form ? $form->title : '',
            ]);
            $created[] = $donation;
            return $donation;
        };

        $bench->measure('donation_create', static function () use ($newDonation, $wpdb) {
            $donation = $newDonation();
            $metaRows = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}give_donationmeta WHERE donation_id = %d",
                $donation->id
            ));
            return ['meta_rows' => $metaRows];
        });

        $updated = $newDonation();
        $bench->measure('donation_update_status', static function () use ($updated) {
            $updated->status = $updated->status->isComplete() ? DonationStatus::PENDING() : DonationStatus::COMPLETE();
            $updated->save();
        });

        foreach ($created as $donation) {
            $donation->delete();
        }
    }
}
