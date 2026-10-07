# Benchmark

`wp give-data bench` is a repeatable measurement of how long GiveWP's everyday work takes on a
large site and how much memory it uses, so every performance change can be measured the same way
and compared with the runs before it. `results/` holds the runs we keep.

## What it measures

Every workload goes through the real code path an admin screen, REST client or template uses:

- the Donations and Donors admin screens: first page, last page, search, and the stats bar
  (`give-api/v2/admin/*`)
- the v3 REST API: donation and donor lists, donor statistics (`givewp/v3/*`)
- campaign totals and goals: `CampaignsDataQuery`, `CampaignDonationQuery`, the 12-campaign grid
- form totals: `give_goal_progress_stats()` and the async column helpers for 20 forms
- reports and statistics: the reports Income route over the past week, which is what the Reports
  screen opens on, `Give_Payment_Stats` over a year, `DonorStatisticsQuery`
- writes: creating a donation and saving a status change, with the number of meta rows one
  donation writes

Lists are read in the site's current donation mode (test or live), the same way the admin screens
read them, and any GiveWP migration still owed runs before measuring starts.

Each workload runs once to warm up and then three times. The result records the median time, the
peak PHP memory and the database queries of one run. A workload whose warm-up takes over a minute
is recorded from that single run and marked as such, so one pathological code path cannot turn a
benchmark into an overnight job. Caches (`give_campaigns_data`, transients,
`Give_Cache`, the object cache) are cleared before the workloads that have one, so totals and
goals are measured uncached. Donations the write workloads create are deleted again at the end.

Every result also records the date, GiveWP version, storage in use, donation and campaign counts,
database version, InnoDB buffer pool size, PHP version and the active plugins with their versions.

The workloads live in `src/DataGenerator/Benchmark/Workloads/`. `Core.php` is GiveWP's; an add-on
such as Peer-to-Peer gets a class of its own there that returns early when the add-on is not
active, listed in `Benchmark::WORKLOADS`. Prefix its workload names with the add-on's slug.

## Running it

On a wp-env site with GiveWP and this plugin active:

```bash
wp give-data bench 100k --donations=100000 --save
wp give-data bench 400k --donations=400000 --save
wp give-data bench 1m --donations=1000000 --save
```

`--donations` tops the site up to that many donations across 50 campaigns before measuring.
Generation goes through the Donation model and takes about 2.5 minutes per 100k. It is additive,
so one site serves all three sizes in turn. Leave the flag off to measure whatever the site holds.

`--save` writes `results/<label>-<storage>-<date>-<version>.json` into this directory (or into
the directory you give it) and the table is printed either way. `--storage=legacy|new` is recorded
so runs against the two storage layouts can be told apart. `--format=json` prints the result
instead of the table.

To compare two saved runs on the host:

```bash
php bin/compare.php benchmarks/results/1m-legacy-20261006-4.18.0.json benchmarks/results/1m-new-20261120-4.19.0.json
```

A stock wp-env has MariaDB's 128 MB default buffer pool, far below what a host gives a site of
this size. To measure with a larger pool, raise it before the run; the value is recorded:

```bash
wp db query "SET GLOBAL innodb_buffer_pool_size = 1073741824"
```

It resets when the container restarts.

## When a number looks wrong

The benchmark says which workload got slower, not why. For that, start the environment with
wp-env's built-in Xdebug profiler and repeat the request:

```bash
npx wp-env start --xdebug=profile
```

Every request then writes a cachegrind file into the container's `/tmp`, which PhpStorm
(Tools > Analyze Xdebug Profiler Snapshot) or qcachegrind opens as a call tree with time per
function. For repeated queries, `define('SAVEQUERIES', true)` in `.wp-env.override.json` and dump
`$wpdb->queries` after the workload, or install Query Monitor and load the screen in the browser.

## Results in this directory

`results/` holds the runs we keep: the project baseline and the before and after of each
performance change worth recording. The numbers depend on the machine that produced them, so
compare runs from the same machine, or treat cross-machine differences as rough.

## Why not in CI

A runner's hardware differs from run to run, so numbers taken there only catch large regressions,
and a before and after on the same machine is what a performance change needs anyway. Run it
locally, commit the result, and compare against the baseline here. A GitHub Action is easy to add
later if a scheduled run turns out to be wanted.
