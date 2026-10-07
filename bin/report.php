<?php

/**
 * Builds benchmarks/report.html from every result in benchmarks/results/: one dot per run on a
 * log time axis for each workload, a second chart for peak memory, and the full table underneath.
 *
 *     php bin/report.php            # writes benchmarks/report.html
 *     php bin/report.php out.html   # writes somewhere else
 *
 * Plain PHP, no WordPress. Re-run it whenever a result lands and commit the page with it.
 *
 * @since 1.2.0
 */

$root = dirname(__DIR__);
$out = $argv[1] ?? $root . '/benchmarks/report.html';

$runs = [];
foreach (glob($root . '/benchmarks/results/*.json') as $file) {
    $run = json_decode(file_get_contents($file), true);
    if ($run && !empty($run['workloads'])) {
        $run['file'] = basename($file);
        $runs[] = $run;
    }
}
if (!$runs) {
    fwrite(STDERR, "No results in benchmarks/results/.\n");
    exit(1);
}
usort($runs, static function (array $a, array $b): int {
    return [$a['donations'], $a['date']] <=> [$b['donations'], $b['date']];
});

// Categorical hues in fixed order, validated for light and dark surfaces; a ninth run folds into gray.
$light = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
$dark = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'];

/*
 * What each workload is, for people who do not read Workloads/Core.php: group, a short label for
 * the chart, and one sentence for the tooltip and the table. A workload missing here still shows,
 * under "Other" with its raw name, so an add-on's workloads appear before anyone documents them.
 */
$catalog = [
    'donations_screen_page1' => ['Admin screens', 'Donations screen, page 1', 'The Donations list table loading its first page of 30: give-api/v2/admin/donations.'],
    'donations_screen_lastpage' => ['Admin screens', 'Donations screen, last page', 'The same list table on its last page, where the offset is largest.'],
    'donations_screen_search_email' => ['Admin screens', 'Donations screen, search by email', 'The Donations list filtered by a donor email.'],
    'donations_screen_stats' => ['Admin screens', 'Donations screen, stats bar', 'The totals strip above the Donations list: give-api/v2/admin/donations/stats.'],
    'donors_screen_page1' => ['Admin screens', 'Donors screen, page 1', 'The Donors list table loading its first page: give-api/v2/admin/donors.'],
    'donors_screen_search_email' => ['Admin screens', 'Donors screen, search by email', 'The Donors list filtered by a donor email.'],
    'donations_api_v3_page1' => ['REST API v3', 'Donations list', 'GET givewp/v3/donations, 30 per page, the public API and newer admin apps.'],
    'donors_api_v3_page1' => ['REST API v3', 'Donors list', 'GET givewp/v3/donors, 30 per page, donors with donations only.'],
    'donor_api_v3_statistics' => ['REST API v3', 'Donor statistics', 'GET givewp/v3/donors/{id}/statistics for one donor.'],
    'campaigns_data_all_uncached' => ['Campaign and form totals', 'All campaign totals, uncached', 'CampaignsDataQuery for every campaign with caches cleared: what refreshes the campaign list totals.'],
    'campaign_grid_12_uncached' => ['Campaign and form totals', 'Campaign grid, 12 campaigns', 'CampaignDonationQuery sum, count and donor count for 12 campaigns, as the campaign grid block does.'],
    'campaign_sum_intended' => ['Campaign and form totals', 'One campaign total', 'CampaignDonationQuery::sumIntendedAmount() for one campaign.'],
    'campaign_by_day_1y' => ['Campaign and form totals', 'Campaign donations by day, 1 year', 'CampaignDonationQuery grouped by day over the past year, the campaign details chart.'],
    'forms_list_20_uncached' => ['Campaign and form totals', 'Forms list totals, 20 forms', 'Goal progress and the async count and revenue columns for 20 forms, as the legacy Forms list computes them.'],
    'reports_income_7d_uncached' => ['Reports and legacy', 'Reports income, past week', 'The Reports page income widget over its default week: give-api/v2/reports/income. Loads every payment in range.'],
    'legacy_stats_earnings_1y_uncached' => ['Reports and legacy', 'Legacy earnings, 1 year', 'Give_Payment_Stats::get_earnings() over the past year, used by legacy reports and add-ons.'],
    'donor_statistics_query' => ['Reports and legacy', 'Donor statistics query', 'DonorStatisticsQuery lifetime, average and count for one donor, behind the donor details screen.'],
    'donation_create' => ['Writes', 'Create a donation', 'Donation::create() through the model with every listener running; meta rows is what one donation writes.'],
    'donation_update_status' => ['Writes', 'Save a status change', 'Flip a donation between pending and complete and save it.'],
];

$groups = [];
foreach ($runs as $run) {
    foreach (array_keys($run['workloads']) as $name) {
        $group = $catalog[$name][0] ?? 'Other';
        $groups[$group][$name] = true;
    }
}
// Catalog order, then anything undocumented last.
$order = array_unique(array_merge(array_column($catalog, 0), array_keys($groups)));
$groups = array_replace(array_flip($order), $groups);
$groups = array_filter($groups, 'is_array');
$groups = array_map('array_keys', $groups);

$label = static function (string $name) use ($catalog): string {
    return $catalog[$name][1] ?? $name;
};
$describe = static function (string $name) use ($catalog): string {
    return $catalog[$name][2] ?? '';
};

$e = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$duration = static function (float $ms): string {
    return $ms >= 1000 ? number_format($ms / 1000, $ms >= 100000 ? 0 : 1) . ' s' : number_format($ms, $ms >= 100 ? 0 : 1) . ' ms';
};
$runName = static function (array $run): string {
    return $run['label'] . ' · ' . number_format($run['donations']) . ' · ' . substr($run['date'], 0, 10) . ' · GiveWP ' . $run['plugin_version'];
};

/**
 * One dot-plot chart. $value picks the figure from a workload, $scale maps it to 0..1.
 */
$chart = static function (string $title, string $unit, callable $value, callable $scale, array $ticks, callable $tickLabel) use ($runs, $groups, $label, $describe, $e, $light, $dark): string {
    $labelWidth = 290;
    $plotWidth = 620;
    $rowHeight = 30;
    $groupHeight = 34;
    $top = 28;
    $height = $top + count($groups) * $groupHeight + array_sum(array_map('count', $groups)) * $rowHeight + 8;
    $width = $labelWidth + $plotWidth + 24;
    $x = static function (float $v) use ($scale, $labelWidth, $plotWidth): float {
        return $labelWidth + $scale($v) * $plotWidth;
    };

    $svg = '<figure class="chart"><figcaption>' . $e($title) . ' <span class="unit">' . $e($unit) . '</span></figcaption>';
    $svg .= '<svg viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . $e($title) . '">';
    foreach ($ticks as $tick) {
        $tx = $x($tick);
        $svg .= '<line class="grid" x1="' . $tx . '" y1="' . $top . '" x2="' . $tx . '" y2="' . ($height - 8) . '"/>';
        $svg .= '<text class="tick" x="' . $tx . '" y="' . ($top - 10) . '" text-anchor="middle">' . $e($tickLabel($tick)) . '</text>';
    }
    $y = $top;
    foreach ($groups as $group => $names) {
        $svg .= '<text class="group" x="' . ($labelWidth - 12) . '" y="' . ($y + $groupHeight - 10) . '" text-anchor="end">' . $e($group) . '</text>';
        $y += $groupHeight;
        foreach ($names as $name) {
            $cy = $y + $rowHeight / 2;
            $y += $rowHeight;
            $svg .= '<text class="label" x="' . ($labelWidth - 12) . '" y="' . ($cy + 4) . '" text-anchor="end">' . $e($label($name)) . '</text>';
            $svg .= '<line class="row" x1="' . $labelWidth . '" y1="' . $cy . '" x2="' . ($labelWidth + $plotWidth) . '" y2="' . $cy . '"/>';
            $last = null;
            $rightmost = $labelWidth;
            foreach ($runs as $r => $run) {
                if (!isset($run['workloads'][$name])) {
                    continue;
                }
                $w = $run['workloads'][$name];
                $v = $value($w);
                $cx = $x($v);
                $capped = !empty($w['capped']);
                $svg .= sprintf(
                    '<circle class="dot%s" style="--c:%s;--cd:%s" cx="%.1f" cy="%.1f" r="5" tabindex="0" data-name="%s" data-describe="%s" data-run="%s" data-value="%s" data-ms="%s" data-mb="%s" data-queries="%s"%s><title>%s</title></circle>',
                    $capped ? ' capped' : '',
                    $light[$r] ?? '#898781',
                    $dark[$r] ?? '#898781',
                    $cx,
                    $cy,
                    $e($label($name)),
                    $e($describe($name)),
                    $e($run['label']),
                    $e($tickLabel($v)),
                    $e($w['ms']),
                    $e($w['peak_mb']),
                    $e($w['queries']),
                    $capped ? ' data-capped="1"' : '',
                    $e($run['label'] . ': ' . $tickLabel($v))
                );
                $last = [$cy, $tickLabel($v)];
                $rightmost = max($rightmost, $cx);
            }
        // Only the latest run is labelled, to the right of every dot in the row; the rest is in the tooltip and the table.
            if ($last) {
                $fits = $rightmost < $labelWidth + $plotWidth - 70;
                $svg .= '<text class="value" x="' . ($rightmost + ($fits ? 10 : -10)) . '" y="' . ($last[0] + 4) . '" text-anchor="' . ($fits ? 'start' : 'end') . '">' . $e($last[1]) . '</text>';
            }
        }
    }
    $svg .= '</svg></figure>';

    return $svg;
};

$maxMs = 1;
$maxMb = 1;
foreach ($runs as $run) {
    foreach ($run['workloads'] as $w) {
        $maxMs = max($maxMs, $w['ms']);
        $maxMb = max($maxMb, $w['peak_mb']);
    }
}
$logMax = max(1, ceil(log10($maxMs)));
$timeTicks = [];
for ($p = 0; $p <= $logMax; $p++) {
    $timeTicks[] = 10 ** $p;
}
$mbTop = ceil($maxMb / 50) * 50;
$mbTicks = range(0, $mbTop, $mbTop / 5);

$timeChart = $chart(
    'Time per request',
    'median of three runs, log scale',
    static function (array $w) {
        return (float)$w['ms'];
    },
    static function (float $v) use ($logMax) {
        return log10(max(1, $v)) / $logMax;
    },
    $timeTicks,
    $duration
);
$memoryChart = $chart(
    'Peak PHP memory',
    'MB, one request',
    static function (array $w) {
        return (float)$w['peak_mb'];
    },
    static function (float $v) use ($mbTop) {
        return $v / $mbTop;
    },
    $mbTicks,
    static function (float $v) {
        return number_format($v) . ' MB';
    }
);

$legend = '<ul class="legend">';
foreach ($runs as $r => $run) {
    $legend .= '<li><span class="swatch" style="--c:' . ($light[$r] ?? '#898781') . ';--cd:' . ($dark[$r] ?? '#898781') . '"></span>' . $e($runName($run)) . '</li>';
}
$legend .= '<li><span class="swatch hollow"></span>hollow: single run, over the one-minute budget</li></ul>';

$table = '<table><thead><tr><th>workload</th>';
foreach ($runs as $run) {
    $table .= '<th>' . $e($run['label']) . '<br><small>' . $e(number_format($run['donations']) . ' · ' . substr($run['date'], 0, 10)) . '</small></th>';
}
$table .= '</tr></thead><tbody>';
foreach ($groups as $group => $names) {
    $table .= '<tr class="group"><th colspan="' . (count($runs) + 1) . '">' . $e($group) . '</th></tr>';
    foreach ($names as $name) {
        $table .= '<tr><th>' . $e($label($name)) . '<br><small>' . $e($name) . '</small>' . ($describe($name) ? '<br><small>' . $e($describe($name)) . '</small>' : '') . '</th>';
        foreach ($runs as $run) {
            $w = $run['workloads'][$name] ?? null;
            $table .= $w
            ? '<td>' . $e($duration($w['ms'])) . (empty($w['capped']) ? '' : '*') . '<br><small>' . $e($w['peak_mb'] . ' MB · ' . number_format($w['queries']) . ' q') . '</small></td>'
            : '<td>-</td>';
        }
        $table .= '</tr>';
    }
}
$table .= '</tbody></table>';

$meta = '<dl class="meta">';
foreach ($runs as $run) {
    $meta .= '<dt>' . $e($run['label']) . '</dt><dd>' . $e(sprintf(
        '%s donations, %d campaigns, storage %s, GiveWP %s, %s, %d MB buffer pool, PHP %s, %s',
        number_format($run['donations']),
        $run['campaigns'],
        $run['storage'],
        $run['plugin_version'],
        $run['db_version'],
        $run['innodb_buffer_pool_mb'],
        $run['php_version'],
        $run['file']
    )) . '</dd>';
}
$meta .= '</dl>';

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GiveWP benchmark</title>
<style>
:root { --surface: #fcfcfb; --page: #f9f9f7; --ink: #0b0b0b; --ink-2: #52514e; --muted: #898781; --grid: #e1e0d9; --axis: #c3c2b7; --tip: #ffffff; }
@media (prefers-color-scheme: dark) { :root { --surface: #1a1a19; --page: #0d0d0d; --ink: #ffffff; --ink-2: #c3c2b7; --muted: #898781; --grid: #2c2c2a; --axis: #383835; --tip: #262624; } .dot, .swatch { --c: var(--cd); } }
* { box-sizing: border-box; }
body { margin: 0; padding: 24px 16px 48px; background: var(--page); color: var(--ink); font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-variant-numeric: tabular-nums; }
main { max-width: 980px; margin: 0 auto; }
h1 { font-size: 22px; margin: 0 0 4px; }
.sub { color: var(--ink-2); margin: 0 0 20px; }
.legend { list-style: none; padding: 0; margin: 0 0 16px; display: flex; flex-wrap: wrap; gap: 6px 20px; color: var(--ink-2); font-size: 14px; }
.legend li { display: flex; align-items: center; gap: 8px; }
.swatch { width: 10px; height: 10px; border-radius: 50%; background: var(--c); flex: none; }
.swatch.hollow { background: none; border: 2px solid var(--muted); }
.chart { margin: 0 0 24px; padding: 16px 8px 8px; background: var(--surface); border-radius: 8px; }
figcaption { font-weight: 600; margin: 0 8px 4px; }
.unit { font-weight: 400; color: var(--muted); margin-left: 6px; }
svg { width: 100%; height: auto; display: block; overflow: visible; }
.grid { stroke: var(--grid); stroke-width: 1; }
.row { stroke: var(--grid); stroke-width: 1; stroke-dasharray: 2 3; }
.tick { fill: var(--muted); font-size: 12px; }
.label { fill: var(--ink-2); font-size: 13px; }
.group { fill: var(--ink); font-size: 13px; font-weight: 600; }
tr.group th { padding-top: 14px; color: var(--ink); font-weight: 600; border-bottom: 2px solid var(--grid); }
.value { fill: var(--ink-2); font-size: 12px; }
.dot { fill: var(--c); stroke: var(--surface); stroke-width: 2; cursor: default; }
.dot.capped { fill: var(--surface); stroke: var(--c); stroke-width: 2.5; }
.dot:hover, .dot:focus { r: 7; outline: none; }
#tip { position: fixed; pointer-events: none; display: none; background: var(--tip); color: var(--ink); border: 1px solid var(--grid); border-radius: 6px; padding: 8px 10px; font-size: 13px; box-shadow: 0 4px 16px rgba(0,0,0,.12); max-width: 320px; }
#tip strong { font-size: 15px; }
#tip .what { margin-top: 6px; color: var(--ink-2); }
#tip .k { display: inline-block; width: 18px; border-top: 2px solid var(--c); vertical-align: middle; margin-right: 6px; }
details { margin-top: 8px; }
summary { cursor: pointer; color: var(--ink-2); }
table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; background: var(--surface); border-radius: 8px; }
th, td { text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--grid); vertical-align: top; }
thead th { color: var(--ink-2); font-weight: 600; }
tbody th { font-weight: 500; }
small { color: var(--muted); }
.meta { font-size: 13px; color: var(--ink-2); }
.meta dt { font-weight: 600; margin-top: 6px; } .meta dd { margin: 0; }
@media (max-width: 600px) { .legend { flex-direction: column; } }
</style>
</head>
<body>
<main>
<h1>GiveWP benchmark</h1>
<p class="sub">Everyday workloads timed through their real code paths with <code>wp give-data bench</code>. Each dot is one saved run; hover or focus a dot for its figures.</p>
{$legend}
{$timeChart}
{$memoryChart}
<details><summary>Every figure as a table (* single run, over budget)</summary>{$table}</details>
<details><summary>Runs</summary>{$meta}</details>
</main>
<div id="tip" role="tooltip"></div>
<script>
(function () {
    var tip = document.getElementById('tip');
    function show(dot, x, y) {
        tip.textContent = '';
        var key = document.createElement('span'); key.className = 'k'; key.style.setProperty('--c', getComputedStyle(dot).fill);
        var strong = document.createElement('strong'); strong.textContent = dot.dataset.value;
        var run = document.createElement('div'); run.appendChild(key); run.appendChild(document.createTextNode(dot.dataset.run + (dot.dataset.capped ? ' (single run, over budget)' : '')));
        var more = document.createElement('div'); more.textContent = dot.dataset.ms + ' ms · ' + dot.dataset.mb + ' MB peak · ' + dot.dataset.queries + ' queries';
        var what = document.createElement('div'); what.className = 'what'; what.textContent = dot.dataset.name + (dot.dataset.describe ? '. ' + dot.dataset.describe : '');
        tip.appendChild(strong); tip.appendChild(run); tip.appendChild(more); tip.appendChild(what);
        tip.style.display = 'block';
        var w = tip.offsetWidth, h = tip.offsetHeight;
        tip.style.left = Math.min(x + 14, window.innerWidth - w - 8) + 'px';
        tip.style.top = (y - h - 12 < 8 ? y + 16 : y - h - 12) + 'px';
    }
    document.querySelectorAll('.dot').forEach(function (dot) {
        dot.addEventListener('pointermove', function (ev) { show(dot, ev.clientX, ev.clientY); });
        dot.addEventListener('pointerleave', function () { tip.style.display = 'none'; });
        dot.addEventListener('focus', function () { var b = dot.getBoundingClientRect(); show(dot, b.left + b.width / 2, b.top); });
        dot.addEventListener('blur', function () { tip.style.display = 'none'; });
    });
})();
</script>
</body>
</html>
HTML;

file_put_contents($out, $html);
echo 'Wrote ' . $out . ' from ' . count($runs) . " runs.\n";
