<?php

namespace GiveDataGenerator\DataGenerator\Benchmark\Give;

use Give\Framework\Migrations\MigrationsRunner;
use Give_Cache;
use GiveDataGenerator\DataGenerator\Benchmark\Benchmark;
use GiveDataGenerator\DataGenerator\Benchmark\Workloads\Core;

/**
 * Everything the benchmark runner needs to know about Give: how to get the site ready, what
 * data it holds, how to clear its caches, and which workloads to run. Another product would
 * supply a class like this one and leave the runner alone.
 *
 * @since 1.2.0
 */
class Adapter
{
    /**
     * Builds a runner with Give's workloads registered.
     *
     * @since 1.2.0
     */
    public function benchmark(?callable $onWorkload = null): Benchmark
    {
        // A fresh site may still owe Give's migrations, the donation meta indexes among them.
        give(MigrationsRunner::class)->run();

        $benchmark = new Benchmark('Give', GIVE_VERSION, [$this, 'clearCaches'], $onWorkload);
        $benchmark->add(new Core(new Fixtures()));

        return $benchmark;
    }

    /**
     * The figures that describe the dataset in a result: how many donations and campaigns.
     *
     * @since 1.2.0
     */
    public function dataset(): array
    {
        global $wpdb;

        return [
            'donations' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'give_payment'"),
            'campaigns' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}give_campaigns"),
        ];
    }

    /**
     * Clears every Give cache: the campaigns data option, transients, and Give_Cache.
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
    }
}
