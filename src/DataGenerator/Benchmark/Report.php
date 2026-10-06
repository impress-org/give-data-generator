<?php

namespace GiveDataGenerator\DataGenerator\Benchmark;

/**
 * Renders benchmark results as text: one run as a table, or two side by side with the change
 * per workload. Plain PHP with no WordPress dependency, so `bin/compare.php` can use it on the host.
 *
 * @since 1.2.0
 */
class Report
{
    /**
     * @since 1.2.0
     */
    public static function table(array $run): string
    {
        $out = self::heading($run) . "\n\n";
        $out .= sprintf("%-36s %12s %10s %8s\n", 'workload', 'median', 'peak MB', 'queries');
        foreach ($run['workloads'] as $name => $workload) {
            $out .= sprintf(
                "%-36s %12s %10s %8d%s\n",
                $name,
                self::duration($workload['ms']),
                $workload['peak_mb'],
                $workload['queries'],
                isset($workload['meta_rows']) ? "  meta rows {$workload['meta_rows']}" : ''
            );
        }

        return $out;
    }

    /**
     * @since 1.2.0
     */
    public static function compare(array $a, array $b): string
    {
        $out = 'A: ' . self::heading($a) . "\nB: " . self::heading($b) . "\n\n";
        $out .= sprintf("%-36s %12s %12s %9s %9s %9s\n", 'workload', 'A', 'B', 'change', 'A MB', 'B MB');
        foreach (array_keys($a['workloads'] + $b['workloads']) as $name) {
            $wa = $a['workloads'][$name] ?? null;
            $wb = $b['workloads'][$name] ?? null;
            if (!$wa || !$wb) {
                $out .= sprintf(
                    "%-36s %12s %12s %9s\n",
                    $name,
                    $wa ? self::duration($wa['ms']) : '-',
                    $wb ? self::duration($wb['ms']) : '-',
                    'n/a'
                );
                continue;
            }
            $change = $wa['ms'] > 0 ? ($wb['ms'] - $wa['ms']) / $wa['ms'] * 100 : 0;
            $out .= sprintf(
                "%-36s %12s %12s %+8.0f%% %9s %9s\n",
                $name,
                self::duration($wa['ms']),
                self::duration($wb['ms']),
                $change,
                $wa['peak_mb'],
                $wb['peak_mb']
            );
        }

        return $out;
    }

    private static function heading(array $run): string
    {
        return sprintf(
            '%s  %s donations, %d campaigns  GiveWP %s  storage %s  %s  pool %d MB  PHP %s',
            $run['label'],
            number_format($run['donations']),
            $run['campaigns'],
            $run['plugin_version'],
            $run['storage'],
            $run['db_version'],
            $run['innodb_buffer_pool_mb'],
            $run['php_version']
        );
    }

    private static function duration(float $ms): string
    {
        return $ms >= 1000 ? number_format($ms / 1000, 2) . ' s' : number_format($ms, 1) . ' ms';
    }
}
