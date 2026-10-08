<?php
/**
 * Prints one saved benchmark result as a table, or two side by side with the change per workload.
 *
 *     php bin/compare.php benchmarks/results/1m-legacy-20261006-4.18.0.json
 *     php bin/compare.php before.json after.json
 *
 * Runs on the host with no WordPress: it only needs `composer install` for the autoloader.
 *
 * @since 1.2.0
 */

use GiveDataGenerator\DataGenerator\Benchmark\Report;

require __DIR__ . '/../vendor/autoload.php';

$files = array_slice($argv, 1);
if (!$files || count($files) > 2) {
    fwrite(STDERR, "Usage: compare.php <result.json> [other.json]\n");
    exit(1);
}

$runs = array_map(static function (string $file): array {
    $run = json_decode((string)file_get_contents($file), true);
    if (!$run || empty($run['workloads'])) {
        fwrite(STDERR, "Not a benchmark result: $file\n");
        exit(1);
    }
    return $run;
}, $files);

echo count($runs) === 1 ? Report::table($runs[0]) : Report::compare($runs[0], $runs[1]);
