# Development

## Setup

```bash
git clone https://github.com/impress-org/give-data-generator.git
cd give-data-generator
composer install
npm install
npm run build
```

`npx @wordpress/env start` then brings up a WordPress at http://localhost:8891 with the latest
GiveWP release and this checkout active (`admin` / `password`). `npm run watch` rebuilds the admin
screen's assets as you edit them.

## Tests

PHPUnit, using GiveWP core's test framework. The bootstrap expects a GiveWP checkout with its
Composer dependencies installed at `../give`, and the same test database GiveWP's own suite uses.

```bash
composer test
composer test -- --filter TestReport
```

## Layout

GiveWP add-ons follow the domain-driven layout of core: `src/Addon/` holds what makes this a
WordPress plugin (activation, environment checks, the GiveWP version check), and `src/DataGenerator/`
holds the feature itself. Domain code never depends on `src/Addon/`.

| path | what |
|:--|:--|
| `src/DataGenerator/AdminSettings.php` | the admin screen and its tabs |
| `src/DataGenerator/DonationGenerator.php`, `CampaignGenerator.php`, `DonationFormGenerator.php`, `SubscriptionGenerator.php`, `PageGenerator.php` | one generator per kind of data; the admin tabs and the CLI both call these |
| `src/DataGenerator/BulkDonationSeeder.php` | donations at scale for the CLI: suspends email and the per-donation cache job, rebuilds totals once at the end, tags what it wrote |
| `src/DataGenerator/CleanUpManager.php` | the Clean up tab |
| `src/DataGenerator/Cli/GiveDataCommand.php` | `wp give-data`: `donations`, `reset`, `pages`, `bench`, `snapshot`, `restore` |
| `src/DataGenerator/Benchmark/Benchmark.php`, `Report.php` | the benchmark runner and text report; product-agnostic, nothing in them knows about Give |
| `src/DataGenerator/Benchmark/Give/` | what the runner needs from Give: fixtures to point workloads at, cache clearing, dataset counts, which workloads to run |
| `src/DataGenerator/Benchmark/Workloads/` | the workloads themselves, `Core.php` for Give; see [benchmarking.md](benchmarking.md) |
| `bin/` | host-side scripts: `compare.php`, `report.php` |
| `benchmarks/results/` | saved benchmark runs and the generated `report.html` |
| `src/DataGenerator/ServiceProvider.php` | registers the generators with GiveWP's container and the hooks and CLI command |

## Conventions

The same as GiveWP core: PSR-12, PHP 7.4, `camelCase` methods, `@since` on every new or changed
method, one changelog line per change in `CHANGELOG.md` under Unreleased.

## Releasing

Bump the version in `give-data-generator.php` and `readme.txt`, move the Unreleased changelog
section under the new version, tag, and publish a GitHub release. The release workflow attaches
the plugin zip.
