<?php

namespace GiveDataGenerator\DataGenerator\Benchmark\Give;

use DateTime;
use Give\Campaigns\Models\Campaign;
use Give\Donors\Models\Donor;
use RuntimeException;

/**
 * The rows the Give workloads point at: a campaign, a donor, the forms, and the date windows,
 * read once from the site before measuring starts.
 *
 * @since 1.2.0
 */
class Fixtures
{
    /** @var Campaign[] */
    public $campaigns;
    /** @var Campaign */
    public $campaign;
    /** @var int[] */
    public $campaignIds;
    /** @var int[] up to 20 default form IDs */
    public $formIds;
    /** @var Donor donor of the most recent donation */
    public $donor;
    /** @var int last page of a 30-per-page donations list */
    public $lastPage;
    /** @var int */
    public $totalDonations;
    /** @var DateTime the newest donation's date, which every window is measured back from */
    public $now;
    /** @var DateTime */
    public $weekAgo;
    /** @var DateTime */
    public $yearAgo;

    /**
     * @since 1.2.0
     *
     * @throws RuntimeException when the site has nothing to measure against.
     */
    public function __construct()
    {
        global $wpdb;

        $this->campaigns = Campaign::query()->getAll() ?: [];
        if (!$this->campaigns) {
            throw new RuntimeException('No campaigns found. Generate data first: wp give-data donations 100000 --campaigns=50');
        }
        $this->campaign = $this->campaigns[0];
        $this->campaignIds = array_map(static function (Campaign $campaign) {
            return $campaign->id;
        }, $this->campaigns);
        $this->formIds = array_slice(array_filter(array_map(static function (Campaign $campaign) {
            $form = $campaign->defaultForm();
            return $form ? $form->id : 0;
        }, $this->campaigns)), 0, 20);
        $this->totalDonations = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'give_payment'");
        $this->lastPage = max(1, (int)ceil($this->totalDonations / 30));
        $donorId = (int)$wpdb->get_var(
            "SELECT meta_value FROM {$wpdb->prefix}give_donationmeta WHERE meta_key = '_give_payment_donor_id'
             AND donation_id = (SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'give_payment')"
        );
        $this->donor = Donor::find($donorId);
        if (!$this->donor) {
            throw new RuntimeException('The most recent donation has no donor. Generate data first: wp give-data donations 100000 --campaigns=50');
        }

        // Date windows hang off the newest donation, not the clock, so a restored snapshot measures
        // the same rows on any day and two runs on the same data read the same windows.
        $newest = $wpdb->get_var("SELECT MAX(post_date) FROM {$wpdb->posts} WHERE post_type = 'give_payment'");
        $this->now = new DateTime($newest ?: 'now');
        $this->weekAgo = (clone $this->now)->modify('-7 days');
        $this->yearAgo = (clone $this->now)->modify('-1 year');
    }
}
