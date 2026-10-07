# Benchmarking GiveWP

`wp give-data bench` times GiveWP's everyday work on a large site: the Donations and Donors
screens, the REST API, campaign and form totals, the Reports page and a donation save. It saves the
numbers so a change can be measured against the runs before it, on the same data, the same way.

This page walks through a first run, then how to read the results, then how to measure a change.
Command and option details are at the end.

## Before you start

You need Docker, Node and PHP on your machine. Everything else runs inside wp-env.

Commands below are written as `wp give-data ...`. On a wp-env site that is
`npx @wordpress/env run cli wp give-data ...`, run from this plugin's directory. On any other site
with WP-CLI, plain `wp` works.

Only ever point the benchmark at a throwaway site. `restore` replaces the whole database.

## First run, about fifteen minutes

1. Start a site with GiveWP and this plugin. This repository ships a `.wp-env.json` that installs
   the latest GiveWP release from WordPress.org next to this checkout:

   ```bash
   cd give-data-generator
   npx @wordpress/env start
   ```

   The site is at http://localhost:8891, user `admin`, password `password`.

2. Load the 100k dataset. It is a database dump kept on this repository's `datasets` release and
   restores in under a minute:

   ```bash
   npx @wordpress/env run cli wp give-data restore https://github.com/impress-org/give-data-generator/releases/download/datasets/100k-givewp-4.18.0.sql.gz --yes
   ```

3. Run the benchmark and save the result:

   ```bash
   npx @wordpress/env run cli wp give-data bench 100k --save
   ```

   It prints each workload's name as it runs, then a table like this:

   ```
   100k  100,000 donations, 50 campaigns  GiveWP 4.18.0  storage legacy  12.3.3-MariaDB  pool 128 MB  PHP 8.3.35

   workload                                   median    peak MB  queries
   donations_screen_page1                     1.09 s         83       33
   donations_screen_stats                     3.04 s         83        1
   reports_income_7d_uncached                 6.00 s       94.2     9419
   donation_create                           10.2 ms       84.2       52  meta rows 25
   ```

   The 100k run takes about a minute. The result is also written to
   `benchmarks/results/100k-legacy-<date>-4.18.0.json`.

4. Draw every saved run on one page and open it:

   ```bash
   php bin/report.php
   open benchmarks/report.html
   ```

That is the whole loop. The 400k and 1M datasets work the same way; 1M restores in about four
minutes and its benchmark takes ten to fifteen.

## Reading the results

**The table** has one row per workload.

| column | meaning |
|:--|:--|
| median | the middle of three timed runs, after one warm-up run that is thrown away |
| peak MB | the most PHP memory one run used (on PHP 8.1 and older the peak cannot be reset between runs, so it is the high-water mark of the whole benchmark so far) |
| queries | database queries one run made |
| meta rows | for `donation_create`, how many donation meta rows one donation wrote |
| single run, over budget | the warm-up took over a minute, so the figure is from that one run |

**The JSON file** holds the same figures plus everything needed to compare runs fairly: date,
GiveWP version, storage in use, donation and campaign counts, database version, InnoDB buffer pool
size, PHP version, active plugins, and all three timed runs per workload under `runs_ms`.

**The report page** is published at https://impress-org.github.io/give-data-generator/ from the
results on `main`, and `benchmarks/report.html` is the same page in the checkout. It shows every
saved result as a series. Workloads are
grouped by what they stand for (admin screens, REST API v3, campaign and form totals, reports and
legacy, writes) with a plain-language label each; the raw name and a one-line description are in
the tooltip and the table. Each dot is one run on a log time axis, so 2 ms and 50 s sit on the
same chart. A hollow
dot is a single run over budget. The second chart is peak memory. Hover or tab to a dot for its
figures, and expand "Every figure as a table" for the numbers.

**Comparing two runs:**

```bash
php bin/compare.php benchmarks/results/1m-legacy-20261007-4.18.0.json benchmarks/results/1m-seq-index-legacy-20261007-4.18.0.json
```

prints both figures per workload and the change as a percentage.

## Measuring a change

The point of the tool. A before and after on the same data, on the same machine:

1. Restore the dataset the change matters at. `restore` puts the site back to exactly the saved
   rows, so the "before" is the committed baseline or a fresh run of your own:

   ```bash
   wp give-data restore <1m dump url> --yes
   wp give-data bench before --save
   ```

2. Switch GiveWP to the branch with the change (or apply it) and run again:

   ```bash
   wp give-data bench after --save
   ```

3. Compare, and look at the report:

   ```bash
   php bin/compare.php benchmarks/results/before-*.json benchmarks/results/after-*.json
   php bin/report.php
   ```

4. Commit the two result files and the regenerated `benchmarks/report.html` with the pull request
   that carries the change, so the numbers live next to what produced them. Once merged, the
   published page picks them up on its own. Give the files a label
   that says what they measure, such as `1m-seq-index`, not `after`.

Numbers depend on the machine that produced them. Compare runs from the same machine, and treat a
difference under about ten percent as noise unless three runs agree.

## Datasets

Three datasets, generated with `wp give-data donations` on GiveWP 4.18.0: 50 campaigns with a
default form each, donations in test mode spread over the three years before 2026-10-07, about one
donor per ten donations.

| dump | donations | size | restore |
|:--|--:|--:|--:|
| `100k-givewp-4.18.0.sql.gz` | 100,000 | 32 MB | under a minute |
| `400k-givewp-4.18.0.sql.gz` | 400,000 | 127 MB | about 2 minutes |
| `1m-givewp-4.18.0.sql.gz` | 1,000,000 | 318 MB | about 4 minutes |

All at `https://github.com/impress-org/give-data-generator/releases/download/datasets/<dump>`.

Date windows in the benchmark (the past week for reports, the past year for campaign charts) are
measured back from the newest donation, not from today, so a dump keeps measuring the same rows as
it ages.

### Generating instead of restoring

`--donations=N` tops the site up to N donations across 50 campaigns before measuring:

```bash
wp give-data bench 100k --donations=100000 --save
```

Generation goes through the Donation model, like real checkouts do, and is slow: about fifteen
minutes per 100k on a quiet machine, hours for 1M. Use it when the dataset itself has to change,
for example after a schema change that the dumps predate.

### Replacing a dump

Generate on a clean site, snapshot, upload:

```bash
npx @wordpress/env clean all
wp give-data donations 100000 --campaigns=50
wp give-data snapshot 100k
gh release upload datasets benchmarks/snapshots/100k-givewp-<version>.sql.gz --clobber
```

`snapshot` writes `benchmarks/snapshots/<label>-givewp-<version>.sql.gz`. That directory is
gitignored; the release is where dumps live. Publishing a new release on this repository triggers
the zip workflow, which attaches a plugin zip named after the tag; delete that asset.

## What is measured

Every workload goes through the real code path an admin screen, REST client or template uses:

- the Donations and Donors admin screens: first page, last page, search, and the stats bar
  (`give-api/v2/admin/*`)
- the v3 REST API: donation and donor lists, donor statistics (`givewp/v3/*`)
- campaign totals and goals: `CampaignsDataQuery`, `CampaignDonationQuery`, the 12-campaign grid
- form totals: `give_goal_progress_stats()` and the async column helpers for 20 forms
- reports and statistics: the reports Income route over the past week, which is what the Reports
  screen opens on, `Give_Payment_Stats` over a year, `DonorStatisticsQuery`
- writes: creating a donation and saving a status change

Lists are read in the site's current donation mode (test or live), the same way the admin screens
read them. Any GiveWP migration still owed runs before measuring starts. Caches
(`give_campaigns_data`, transients, `Give_Cache`, the object cache) are cleared before the
workloads that have one, so totals and goals are measured uncached. Donations the write workloads
create are deleted again at the end.

The workloads live in `src/DataGenerator/Benchmark/Workloads/`. `Core.php` is GiveWP's. An add-on
such as Peer-to-Peer gets a class of its own there, listed in `Benchmark::WORKLOADS`, that returns
early when the add-on is not active. Prefix its workload names with the add-on's slug.

## Commands

```
wp give-data bench <label> [--donations=<n>] [--storage=legacy|new] [--save[=<dir>]] [--format=table|json]
```

Times the workloads on the current site. `--donations` tops the site up first. `--storage` is
recorded in the result so runs against the two storage layouts can be told apart; it does not
switch anything. `--save` writes the JSON result into `benchmarks/results/`, or into the directory
you give it. `--format=json` prints the result instead of the table.

```
wp give-data snapshot <label> [--dir=<dir>]
```

Dumps the whole database to `benchmarks/snapshots/<label>-givewp-<version>.sql.gz`.

```
wp give-data restore <path-or-url> [--yes]
```

Replaces every table on the site with the dump. Asks first unless `--yes`. The site keeps its own
active plugins and, under wp-env, its own URL; elsewhere the dump's URL is rewritten to the
site's.

```
php bin/compare.php <result.json> [other.json]
php bin/report.php [out.html]
```

Host-side, no WordPress needed beyond `composer install` for the autoloader.

## When a number looks wrong

The benchmark says which workload got slower, not why. Start the environment with wp-env's
built-in Xdebug profiler and repeat the request:

```bash
npx @wordpress/env start --xdebug=profile
```

Every request then writes a cachegrind file into the container's `/tmp`, which PhpStorm
(Tools > Analyze Xdebug Profiler Snapshot) or qcachegrind opens as a call tree with time per
function. For repeated queries, `define('SAVEQUERIES', true)` in `.wp-env.override.json` and dump
`$wpdb->queries` after the workload, or install Query Monitor and load the screen in the browser.

A stock wp-env has MariaDB's 128 MB default buffer pool, far below what a host gives a site of this
size. To measure with a larger pool, raise it before the run; the value is recorded in the result
and resets when the container restarts:

```bash
wp db query "SET GLOBAL innodb_buffer_pool_size = 1073741824"
```

## Why it runs locally and not in CI

A runner's hardware differs from run to run, so numbers taken there only catch large regressions,
and a before and after on the same machine is what a performance change needs anyway. Run it
locally, commit the result, compare against the baseline. A GitHub Action is easy to add later if
a scheduled run turns out to be wanted.
