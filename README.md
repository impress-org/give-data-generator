# GiveWP Data Generator

Test data for GiveWP sites: donations, donors, campaigns, forms, subscriptions and pages, from a
few rows in the admin screen to a million donations from the command line. It also benchmarks the
site it filled, so performance work on GiveWP is measured the same way every time.

For development and testing only. Never run it on a site with real donations.

## Install

1. Download the latest zip from [releases](https://github.com/impress-org/give-data-generator/releases)
   and install it as a plugin, or clone this repository into `wp-content/plugins/` and run
   `composer install --no-dev`.
2. Activate it. GiveWP 4.0 or later must be active.
3. Find it under **Donations > Data Generator**.

## Docs

- [The admin screen](docs/admin-screen.md): campaigns, donations, forms, subscriptions, pages and
  clean up, a tab each, up to 1,000 rows per request.
- [Command line](docs/cli.md): `wp give-data donations` for hundreds of thousands of donations,
  `reset` to remove them, `pages` for a page per block and shortcode.
- [Benchmarking](docs/benchmarking.md): a first run in fifteen minutes, how to read the results,
  how to measure a change before and after, and where the datasets live.
- [Development](docs/development.md): setup, tests, layout and releasing.

## Requirements

WordPress 6.9+, PHP 7.4+, GiveWP 4.0+.

## License

GPL-3.0.
