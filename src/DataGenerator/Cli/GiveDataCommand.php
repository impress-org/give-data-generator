<?php

namespace GiveDataGenerator\DataGenerator\Cli;

use Exception;
use GiveDataGenerator\DataGenerator\BulkDonationSeeder;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Generate GiveWP test data at scale from the command line.
 *
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
     * : How many donations to add. About 2.5 minutes per 100,000.
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

        /** @var BulkDonationSeeder $seeder */
        $seeder = give(BulkDonationSeeder::class);
        $started = microtime(true);

        WP_CLI::log(sprintf('Ensuring %d campaigns with default forms...', $campaignCount));
        $campaigns = $seeder->ensureCampaigns($campaignCount);

        $progress = Utils\make_progress_bar(sprintf('Creating %s donations', number_format($count)), $count);
        $ticked = 0;

        try {
            $seeder->seed($campaigns, $count, $donorTarget, $mode, $status, static function (int $created) use ($progress, &$ticked) {
                $progress->tick($created - $ticked);
                $ticked = $created;
            });
        } catch (Exception $e) {
            $progress->finish();
            WP_CLI::error($e->getMessage());
        }

        $progress->finish();

        WP_CLI::success(sprintf(
            'Site now holds %s donations and %s donors across %d campaigns (%ds).',
            number_format($seeder->countDonations()),
            number_format($seeder->countDonors()),
            count($campaigns),
            round(microtime(true) - $started)
        ));
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
}
