<?php

namespace GiveDataGenerator\DataGenerator\Benchmark;

use RuntimeException;
use WP_REST_Request;

/**
 * Times workloads against whatever data the site holds and returns one result document.
 *
 * The runner knows nothing about Give. A product hands it a name and version, a callable that
 * clears the product's caches, and workload objects that register themselves through
 * {@see measure()}; {@see Give\Adapter} does that for Give. Every workload runs once to warm
 * up and then three times. The median is kept, with the warm-up's own time as the first-load
 * figure, the spread of the timed runs, peak PHP memory (per run on PHP 8.2+, cumulative before
 * that) and the database queries one run makes. A workload slower than {@see BUDGET_MS} on its
 * warm-up is recorded from that run alone.
 *
 * @since 1.2.0
 */
class Benchmark
{
    /**
     * A workload whose warm-up run takes longer than this is measured by that one run alone, so
     * a pathological code path costs the benchmark minutes rather than hours.
     */
    const BUDGET_MS = 60000;

    /** @var string */
    private $product;
    /** @var string */
    private $version;
    /** @var callable */
    private $clearCaches;
    /** @var callable|null receives each workload name before it runs */
    private $onWorkload;
    /** @var object[] each with register(Benchmark $benchmark): void */
    private $workloads = [];
    /** @var array<string, array> */
    private $results = [];
    /** @var bool */
    private $canResetPeak;

    /**
     * @since 1.2.0
     *
     * @param callable $clearCaches clears every cache the product keeps, so totals can be measured cold
     */
    public function __construct(string $product, string $version, callable $clearCaches, ?callable $onWorkload = null)
    {
        $this->product = $product;
        $this->version = $version;
        $this->clearCaches = $clearCaches;
        $this->onWorkload = $onWorkload;
        $this->canResetPeak = function_exists('memory_reset_peak_usage');
    }

    /**
     * Adds a workload object; its register() method is called when the benchmark runs.
     *
     * @since 1.2.0
     */
    public function add(object $workloads): self
    {
        $this->workloads[] = $workloads;

        return $this;
    }

    /**
     * Runs every workload and returns the result document. $dataset describes the data measured
     * (for Give, donation and campaign counts) and is merged into the document as given.
     *
     * @since 1.2.0
     */
    public function run(string $label, string $storage, array $dataset = []): array
    {
        global $wpdb;

        wp_set_current_user(1);
        // Real sends are not a measurement, and a CLI container has no mail transport anyway.
        add_filter('pre_wp_mail', '__return_true');
        // Whatever ran before measuring (migrations, data generation) must not leave the object cache warm.
        wp_cache_flush();

        foreach ($this->workloads as $workloads) {
            $workloads->register($this);
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
            'product' => $this->product,
            'plugin_version' => $this->version,
            'generator_version' => GIVE_DATA_GENERATOR_VERSION,
            'storage' => $storage,
        ] + $dataset + [
            'db_version' => $wpdb->get_var('SELECT VERSION()'),
            'innodb_buffer_pool_mb' => (int)round((int)$wpdb->get_var('SELECT @@innodb_buffer_pool_size') / 1048576),
            'php_version' => PHP_VERSION,
            'peak_memory_is_per_run' => $this->canResetPeak,
            'plugins' => $plugins,
            'workloads' => $this->results,
        ];
    }

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
        $median = $times[intdiv(count($times), 2)];
        $mean = array_sum($times) / count($times);
        // Sample standard deviation of the timed runs; with one run there is no spread to report.
        $stdev = count($times) > 1
            ? sqrt(array_sum(array_map(static function ($t) use ($mean) {
                return ($t - $mean) ** 2;
            }, $times)) / (count($times) - 1))
            : 0.0;

        $this->results[$name] = array_merge([
            'ms' => round($median, 1),
            'stdev_ms' => round($stdev, 1),
            'stdev_pct' => $median > 0 ? round($stdev / $median * 100, 1) : 0.0,
            // The first load, before the database and object caches are warm. Not part of the median.
            'first_ms' => round($runs[0]['ms'], 1),
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
     * Clears every cache the product keeps, so totals and goals are measured cold. Pass as a setup.
     *
     * @since 1.2.0
     */
    public function clearCaches(): void
    {
        ($this->clearCaches)();
        wp_cache_flush();
    }
}
