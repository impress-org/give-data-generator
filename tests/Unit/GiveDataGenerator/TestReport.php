<?php

namespace GiveDataGenerator\Tests\Unit\GiveDataGenerator;

use Give\Tests\TestCase;
use GiveDataGenerator\DataGenerator\Benchmark\Report;

/**
 * @since 1.2.0
 */
class TestReport extends TestCase
{
    public function testCompareShowsChangePerWorkloadAndMarksMissingOnes(): void
    {
        $before = $this->result('before', ['donations_screen_page1' => 200.0, 'reports_income_1y_uncached' => 32000.0]);
        $after = $this->result('after', ['donations_screen_page1' => 50.0, 'donation_create' => 10.0]);

        $out = Report::compare($before, $after);

        $this->assertStringContainsString('A: before  1,000,000 donations', $out);
        $this->assertMatchesRegularExpression('/donations_screen_page1\s+200\.0 ms\s+50\.0 ms\s+-75%/', $out);
        $this->assertMatchesRegularExpression('/reports_income_1y_uncached\s+32\.00 s\s+-\s+n\/a/', $out);
        $this->assertMatchesRegularExpression('/donation_create\s+-\s+10\.0 ms\s+n\/a/', $out);
    }

    public function testTableListsEveryWorkloadWithExtraFigures(): void
    {
        $run = $this->result('1m', ['donation_create' => 11.1]);
        $run['workloads']['donation_create']['meta_rows'] = 25;

        $out = Report::table($run);

        $this->assertMatchesRegularExpression('/donation_create\s+11\.1 ms\s+-\s+-\s+80\s+3  meta rows 25/', $out);
    }

    private function result(string $label, array $workloads): array
    {
        return [
            'label' => $label,
            'donations' => 1000000,
            'campaigns' => 50,
            'plugin_version' => '4.18.0',
            'storage' => 'legacy',
            'db_version' => '12.3.3-MariaDB',
            'innodb_buffer_pool_mb' => 128,
            'php_version' => '8.3.0',
            'workloads' => array_map(static function (float $ms): array {
                return ['ms' => $ms, 'peak_mb' => 80, 'queries' => 3];
            }, $workloads),
        ];
    }
}
