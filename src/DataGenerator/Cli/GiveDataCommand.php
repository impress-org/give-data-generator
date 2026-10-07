<?php

namespace GiveDataGenerator\DataGenerator\Cli;

use Exception;
use Give\Campaigns\Models\Campaign;
use GiveDataGenerator\DataGenerator\Benchmark\Benchmark;
use GiveDataGenerator\DataGenerator\Benchmark\Report;
use GiveDataGenerator\DataGenerator\BulkDonationSeeder;
use GiveDataGenerator\DataGenerator\PageGenerator;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Generate GiveWP test data at scale from the command line, and benchmark the site it produced.
 *
 * @since 1.2.0 Add the bench subcommand.
 * @since 1.1.0
 */
class GiveDataCommand
{
    /**
     * Add donations, dated over the last three years and spread across campaigns.
     *
     * ## OPTIONS
     *
     * <count>
     * : How many donations to add. Progress is logged every ten seconds.
     *
     * [--campaigns=<number>]
     * : Campaigns (each with a default form) to spread donations across. Missing ones are created.
     * ---
     * default: 10
     * ---
     *
     * [--donors=<number>]
     * : Donors the site should hold before existing donors are reused. Default: count / 10.
     *
     * [--mode=<mode>]
     * : Donation mode.
     * ---
     * default: test
     * options:
     *   - test
     *   - live
     * ---
     *
     * [--status=<status>]
     * : Donation status, or "random" for a realistic mix (70% complete).
     * ---
     * default: random
     * ---
     *
     * ## EXAMPLES
     *
     *     wp give-data donations 1000000 --campaigns=50 --donors=100000
     *     wp give-data donations 5000 --mode=live --status=complete
     *
     * @since 1.1.0
     *
     * @subcommand donations
     */
    public function donations(array $args, array $assocArgs): void
    {
        $count = (int)($args[0] ?? 0);
        if ($count < 1) {
            WP_CLI::error('Give a positive number of donations to add.');
        }

        $campaignCount = max(1, (int)Utils\get_flag_value($assocArgs, 'campaigns', 10));
        $donorTarget = max(1, (int)Utils\get_flag_value($assocArgs, 'donors', intdiv($count, 10)));
        $mode = Utils\get_flag_value($assocArgs, 'mode', 'test');
        $status = Utils\get_flag_value($assocArgs, 'status', 'random');

        $started = microtime(true);
        $seeder = $this->seed($count, $campaignCount, $donorTarget, $mode, $status);

        WP_CLI::success(sprintf(
            'Site now holds %s donations and %s donors across %d campaigns (%ds).',
            number_format($seeder->countDonations()),
            number_format($seeder->countDonors()),
            $campaignCount,
            round(microtime(true) - $started)
        ));
    }

    /**
     * Time GiveWP's everyday workloads on this site and save the numbers for comparison.
     *
     * Every workload runs through the real code path an admin screen, REST client or template
     * uses: the Donations and Donors list endpoints, the v3 REST API, campaign and form totals
     * with caches cleared, reports, and a donation save. Each runs once to warm up and then three
     * times; the median, peak PHP memory and query count are recorded along with the date, GiveWP
     * version, storage in use, database version and InnoDB buffer pool size.
     *
     * ## OPTIONS
     *
     * <label>
     * : A name for this run, such as the dataset size: 100k, 400k, 1m.
     *
     * [--donations=<count>]
     * : Top the site up to this many donations across 50 campaigns before measuring.
     *
     * [--storage=<storage>]
     * : Donation storage the site is using, recorded in the result.
     * ---
     * default: legacy
     * options:
     *   - legacy
     *   - new
     * ---
     *
     * [--save[=<dir>]]
     * : Write the result as JSON into this directory, named <label>-<storage>-<date>-<version>.json.
     * Without a value it goes into the plugin's own benchmarks/results directory.
     *
     * [--format=<format>]
     * : What to print.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp give-data bench 1m --donations=1000000 --save
     *     wp give-data bench after-fix --format=json > after.json
     *
     * @since 1.2.0
     *
     * @subcommand bench
     */
    public function bench(array $args, array $assocArgs): void
    {
        $label = $args[0];
        $target = (int)Utils\get_flag_value($assocArgs, 'donations', 0);
        $storage = Utils\get_flag_value($assocArgs, 'storage', 'legacy');
        $save = Utils\get_flag_value($assocArgs, 'save', false);
        $format = Utils\get_flag_value($assocArgs, 'format', 'table');

        if ($target > 0) {
            $current = give(BulkDonationSeeder::class)->countDonations();
            if ($current < $target) {
                WP_CLI::log(sprintf('Site has %s donations; adding %s...', number_format($current), number_format($target - $current)));
                $this->seed($target - $current, 50, intdiv($target, 10), 'test', 'random');
            }
        }

        try {
            $benchmark = new Benchmark(static function (string $name) {
                WP_CLI::log('  ' . $name);
            });
            $result = $benchmark->run($label, $storage);
        } catch (Exception $e) {
            WP_CLI::error($e->getMessage());
        }

        if ($save !== false) {
            $dir = $save === true ? GIVE_DATA_GENERATOR_DIR . 'benchmarks/results' : $save;
            if (!wp_mkdir_p($dir)) {
                WP_CLI::error("Could not create $dir.");
            }
            $file = sprintf('%s/%s-%s-%s-%s.json', rtrim($dir, '/'), $label, $storage, gmdate('Ymd'), GIVE_VERSION);
            file_put_contents($file, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            WP_CLI::log("Saved $file");
        }

        WP_CLI::line($format === 'json' ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : Report::table($result));
    }

    /**
     * Save the whole database as a gzipped SQL dump, so a dataset can be restored instead of generated again.
     *
     * ## OPTIONS
     *
     * <label>
     * : A name for the dataset, such as 100k, 400k or 1m. The file is <label>-givewp-<version>.sql.gz.
     *
     * [--dir=<dir>]
     * : Where to write it. Defaults to the plugin's own benchmarks/snapshots directory.
     *
     * ## EXAMPLES
     *
     *     wp give-data snapshot 1m
     *
     * @since 1.2.0
     *
     * @subcommand snapshot
     */
    public function snapshot(array $args, array $assocArgs): void
    {
        $dir = rtrim(Utils\get_flag_value($assocArgs, 'dir', GIVE_DATA_GENERATOR_DIR . 'benchmarks/snapshots'), '/');
        if (!wp_mkdir_p($dir)) {
            WP_CLI::error("Could not create $dir.");
        }
        $file = sprintf('%s/%s-givewp-%s.sql.gz', $dir, $args[0], GIVE_VERSION);
        $sql = $file . '.tmp.sql';
        $started = microtime(true);

        WP_CLI::log('Exporting the database...');
        $this->runInProcess('db export ' . escapeshellarg($sql) . ' --add-drop-table --single-transaction --quick');

        WP_CLI::log('Compressing...');
        $this->copyStream(fopen($sql, 'rb'), gzopen($file, 'wb6'));
        unlink($sql);

        WP_CLI::success(sprintf('Saved %s (%s, %ds).', $file, size_format(filesize($file)), round(microtime(true) - $started)));
    }

    /**
     * Replace the database with a snapshot taken by `wp give-data snapshot`. Everything on the site is replaced.
     *
     * ## OPTIONS
     *
     * <source>
     * : Path or URL of a .sql.gz snapshot.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp give-data restore benchmarks/snapshots/1m-givewp-4.18.0.sql.gz
     *     wp give-data restore https://github.com/impress-org/give-data-generator/releases/download/datasets/1m-givewp-4.18.0.sql.gz
     *
     * @since 1.2.0
     *
     * @subcommand restore
     */
    public function restore(array $args, array $assocArgs): void
    {
        $source = $args[0];
        WP_CLI::confirm("Replace every table on this site with $source?", $assocArgs);
        $started = microtime(true);
        $home = get_option('home');

        WP_CLI::log('Unpacking...');
        // db import reads the extension, so the dump needs a .sql name; drop the empty file tempnam made.
        $tmp = tempnam(get_temp_dir(), 'give-restore-');
        $sql = $tmp . '.sql';
        unlink($tmp);
        $in = @fopen('compress.zlib://' . $source, 'rb');
        if (!$in) {
            WP_CLI::error("Could not open $source.");
        }
        $this->copyStream($in, fopen($sql, 'wb'));

        WP_CLI::log('Importing...');
        $this->runInProcess('db reset --yes');
        $this->runInProcess('db import ' . escapeshellarg($sql));
        unlink($sql);

        // wp-env pins the URL with WP_HOME and WP_SITEURL; elsewhere the dump's URL has to be rewritten.
        wp_cache_flush();
        $restoredHome = get_option('home');
        if ($restoredHome !== $home) {
            WP_CLI::log("Rewriting $restoredHome to $home...");
            $this->runInProcess(sprintf('search-replace %s %s --all-tables --skip-columns=guid --quiet', escapeshellarg($restoredHome), escapeshellarg($home)));
        }

        WP_CLI::success(sprintf('Restored %s (%ds).', $source, round(microtime(true) - $started)));
    }

    /**
     * Runs another WP-CLI command in this process. A child process would start without the
     * container's environment, and wp-env's wp-config reads the database credentials from it.
     *
     * @since 1.2.0
     */
    private function runInProcess(string $command): void
    {
        WP_CLI::runcommand($command, ['launch' => false, 'exit_error' => true]);
    }

    /**
     * Copies one stream into another in chunks; gzopen handles compression on either side.
     *
     * @since 1.2.0
     *
     * @param resource $in
     * @param resource $out
     */
    private function copyStream($in, $out): void
    {
        while (!feof($in)) {
            fwrite($out, fread($in, 1048576));
        }
        fclose($in);
        fclose($out);
    }

    /**
     * Ensures the campaigns exist, then adds donations, logging progress as it goes.
     *
     * @since 1.2.0
     */
    private function seed(int $count, int $campaignCount, int $donorTarget, string $mode, string $status): BulkDonationSeeder
    {
        /** @var BulkDonationSeeder $seeder */
        $seeder = give(BulkDonationSeeder::class);
        $started = microtime(true);

        WP_CLI::log(sprintf('Ensuring %d campaigns with default forms...', $campaignCount));
        $campaigns = $seeder->ensureCampaigns($campaignCount);

        // Plain log lines instead of a progress bar: a redrawn bar wraps and repeats in some shells
        // and is useless in piped output. One line every ten seconds is enough for a 25 minute run.
        $lastLog = $started;
        $clock = static function (float $seconds): string {
            $seconds = (int)$seconds;
            return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds, 60) % 60, $seconds % 60);
        };
        $report = static function (int $created) use ($count, $started, &$lastLog, $clock) {
            if ($created < $count && microtime(true) - $lastLog < 10) {
                return;
            }
            $lastLog = microtime(true);
            $elapsed = $lastLog - $started;
            $left = $created > 0 ? ($count - $created) * $elapsed / $created : 0;
            WP_CLI::log(sprintf(
                '%s / %s (%d%%)  %s elapsed, about %s left',
                number_format($created),
                number_format($count),
                $created * 100 / $count,
                $clock($elapsed),
                $clock($left)
            ));
        };

        try {
            $seeder->seed($campaigns, $count, $donorTarget, $mode, $status, $report);
        } catch (Exception $e) {
            WP_CLI::error($e->getMessage());
        }

        return $seeder;
    }

    /**
     * Delete every donation and donor this command generated. Real data is untouched.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * @since 1.1.0
     *
     * @subcommand reset
     */
    public function reset(array $args, array $assocArgs): void
    {
        /** @var BulkDonationSeeder $seeder */
        $seeder = give(BulkDonationSeeder::class);

        WP_CLI::confirm(sprintf('Delete %s generated donations and their generated donors?', number_format($seeder->countGenerated())), $assocArgs);

        $result = $seeder->reset();

        WP_CLI::success(sprintf('Deleted %s donations and %s donors.', number_format($result['donations']), number_format($result['donors'])));
    }

    /**
     * Create published pages that show GiveWP blocks and shortcodes.
     *
     * ## OPTIONS
     *
     * [--campaign=<id>]
     * : Campaign the campaign blocks use. Form blocks use its default form.
     *
     * [--form=<id>]
     * : Donation form to use instead of a campaign, for a standalone form. Skips the campaign blocks and shortcodes.
     *
     * [--layout=<layout>]
     * : One page per block or shortcode, a blocks page and a shortcodes page, or everything on one page.
     * ---
     * default: individual
     * options:
     *   - individual
     *   - type
     *   - single
     * ---
     *
     * [--only=<names>]
     * : Comma-separated block names or shortcode tags to create. Default: all of them.
     *
     * ## EXAMPLES
     *
     *     wp give-data pages --campaign=12
     *     wp give-data pages --form=34 --layout=single
     *     wp give-data pages --campaign=12 --only=givewp/campaign-grid,give_form
     *
     * @unreleased
     *
     * @subcommand pages
     */
    public function pages(array $args, array $assocArgs): void
    {
        $campaignId = Utils\get_flag_value($assocArgs, 'campaign');
        $formId = Utils\get_flag_value($assocArgs, 'form');

        if (($campaignId === null) === ($formId === null)) {
            WP_CLI::error('Pass either --campaign=<id> or --form=<id>.');
        }

        if ($campaignId !== null) {
            $campaign = Campaign::find(absint($campaignId));
            if (!$campaign) {
                WP_CLI::error(sprintf('Campaign %s not found.', $campaignId));
            }
            $campaignId = $campaign->id;
            $formId = (int)$campaign->defaultFormId;
        } elseif (get_post_type(absint($formId)) !== 'give_forms') {
            WP_CLI::error(sprintf('Donation form %s not found.', $formId));
        }

        /** @var PageGenerator $generator */
        $generator = give(PageGenerator::class);

        // "Block: givewp/campaign-grid" => "givewp/campaign-grid", "Shortcode: [give_form]" => "give_form"
        $titles = [];
        foreach (array_keys($generator->getPageContents((int)$formId, $campaignId)) as $title) {
            $titles[preg_replace('/^(Block: |Shortcode: \[)|\]$/', '', $title)] = $title;
        }

        $only = Utils\get_flag_value($assocArgs, 'only');
        if ($only !== null) {
            $names = array_map('trim', explode(',', $only));
            $unknown = array_diff($names, array_keys($titles));
            if ($unknown) {
                WP_CLI::error(sprintf("Unknown: %s\nAvailable: %s", implode(', ', $unknown), implode(', ', array_keys($titles))));
            }
            $titles = array_intersect_key($titles, array_flip($names));
        }

        try {
            $pageIds = $generator->generatePages(array_values($titles), (int)$formId, $campaignId, Utils\get_flag_value($assocArgs, 'layout', 'individual'));
        } catch (Exception $e) {
            WP_CLI::error($e->getMessage());
        }

        foreach ($pageIds as $pageId) {
            WP_CLI::log(sprintf('%d  %s  %s', $pageId, get_the_title($pageId), get_permalink($pageId)));
        }

        WP_CLI::success(sprintf('Created %d pages.', count($pageIds)));
    }
}
