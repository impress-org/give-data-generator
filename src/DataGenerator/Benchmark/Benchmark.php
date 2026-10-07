<?php

namespace GiveDataGenerator\DataGenerator\Benchmark;

use DateTime;
use Give\Campaigns\Models\Campaign;
use Give\Donors\Models\Donor;
use Give\Framework\Migrations\MigrationsRunner;
use Give_Cache;
use GiveDataGenerator\DataGenerator\Benchmark\Workloads\Core;
use RuntimeException;
use WP_REST_Request;

/**
 * Times GiveWP's everyday read and write workloads against whatever data the site holds.
 *
 * Every workload goes through the real code path an admin screen, REST client or template would
 * use. Each runs once to warm up and then three times; the median is kept, along with peak PHP
 * memory (per run on PHP 8.2+, cumulative before that) and the database queries one run makes.
 * A workload slower than {@see BUDGET_MS} on its warm-up is recorded from that run alone.
 * Workload classes register themselves through {@see measure()}; add one to {@see WORKLOADS} to
 * include it, and have it bail when the plugin it measures is not active.
 *
 * @since 1.2.0
 */
class Benchmark
{
    /**
     * Workload classes, in the order they run. Each has `register(Benchmark $benchmark): void`.
     */
    const WORKLOADS = [
        Core::class,
    ];

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
    /** @var DateTime */
    public $weekAgo;
    /** @var DateTime */
    public $yearAgo;
    /** @var DateTime */
    public $now;

    /** @var callable|null receives each workload name before it runs */
    private $onWorkload;
    /** @var array<string, array> */
    private $results = [];
    /** @var bool */
    private $canResetPeak;

    /**
     * @since 1.2.0
     *
     * @throws RuntimeException when the site has no campaigns to measure against.
     */
    public function __construct(?callable $onWorkload = null)
    {
        global $wpdb;

        $this->onWorkload = $onWorkload;
        $this->canResetPeak = function_exists('memory_reset_peak_usage');

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
        $this->weekAgo = new DateTime('-7 days');
        $this->yearAgo = new DateTime('-1 year');
        $this->now = new DateTime();
    }

    /**
     * Runs every workload and returns the result document.
     *
     * @since 1.2.0
     */
    public function run(string $label, string $storage): array
    {
        global $wpdb;

        wp_set_current_user(1);
        // Real sends are not a storage cost, and a CLI container has no mail transport anyway.
        add_filter('pre_wp_mail', '__return_true');
        // A fresh site may still owe GiveWP's migrations, the donation meta indexes among them.
        give(MigrationsRunner::class)->run();

        foreach (self::WORKLOADS as $workloads) {
            (new $workloads())->register($this);
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugins = [];
        foreach (get_option('active_plugins', []) as $plugin) {
            // The option can still name a plugin whose directory is gone, as on a dev site that remounts.
            if (file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
                $plugins[dirname($plugin)] = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false)['Version'];
            }
        }

        return [
            'label' => $label,
            'date' => gmdate('c'),
            'plugin_version' => GIVE_VERSION,
            'generator_version' => GIVE_DATA_GENERATOR_VERSION,
            'storage' => $storage,
            'donations' => $this->totalDonations,
            'campaigns' => count($this->campaigns),
            'db_version' => $wpdb->get_var('SELECT VERSION()'),
            'innodb_buffer_pool_mb' => (int)round((int)$wpdb->get_var('SELECT @@innodb_buffer_pool_size') / 1048576),
            'php_version' => PHP_VERSION,
            'peak_memory_is_per_run' => $this->canResetPeak,
            'plugins' => $plugins,
            'workloads' => $this->results,
        ];
    }

    /**
     * A workload whose warm-up run takes longer than this is measured by that one run alone, so
     * a pathological code path costs the benchmark minutes rather than hours.
     */
    const BUDGET_MS = 60000;

    /**
     * Times a workload: one warm-up, then three runs with $setup before each, keeping the median.
     * A workload may return an array of extra figures to record alongside the timing, such as
     * rows written.
     *
     * @since 1.2.0
     */
    public function measure(string $name, callable $workload, ?callable $setup = null): void
    {
        global $wpdb;

        if ($this->onWorkload) {
            ($this->onWorkload)($name);
        }

        $runs = [];
        for ($i = 0; $i < 4; $i++) {
            if ($setup) {
                $setup();
            }
            if ($this->canResetPeak) {
                memory_reset_peak_usage();
            }
            $queriesBefore = $wpdb->num_queries;
            $start = microtime(true);
            $returned = $workload();
            $runs[] = [
                'ms' => (microtime(true) - $start) * 1000,
                'peak' => memory_get_peak_usage(),
                'queries' => $wpdb->num_queries - $queriesBefore,
                'extra' => is_array($returned) ? $returned : [],
            ];
            if ($i === 0 && $runs[0]['ms'] > self::BUDGET_MS) {
                break;
            }
        }

        $capped = count($runs) === 1;
        $timed = $capped ? $runs : array_slice($runs, 1);
        $times = array_column($timed, 'ms');
        sort($times);

        $this->results[$name] = array_merge([
            'ms' => round($times[intdiv(count($times), 2)], 1),
            'runs_ms' => array_map(static function ($time) {
                return round($time, 1);
            }, $times),
            'peak_mb' => round(max(array_column($timed, 'peak')) / 1048576, 1),
            'queries' => end($timed)['queries'],
        ], $capped ? ['capped' => true] : [], end($timed)['extra']);
    }

    /**
     * Performs an internal GET request as the current user and throws on an error response.
     *
     * @since 1.2.0
     */
    public function rest(string $route, array $params = []): void
    {
        $request = new WP_REST_Request('GET', $route);
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        $response = rest_do_request($request);
        if ($response->is_error()) {
            throw new RuntimeException($route . ': ' . $response->as_error()->get_error_message());
        }
    }

    /**
     * Clears every GiveWP cache so totals and goals are measured uncached. Pass as a setup.
     *
     * @since 1.2.0
     */
    public function clearCaches(): void
    {
        global $wpdb;

        delete_option('give_campaigns_data');
        delete_option('give_campaigns_subscriptions_data');
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%give%'
             OR option_name LIKE '\_transient\_timeout\_%give%' OR option_name LIKE 'give\_cache\_%'"
        );
        Give_Cache::flush_cache(true);
        wp_cache_flush();
    }
}
