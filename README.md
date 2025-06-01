# GiveWP - Data Generator

A WordPress plugin that generates test data for GiveWP including donations, donors, and more. This add-on is designed for testing and development purposes only.

## Features

- Generate test donations using the GiveWP Donation Model
- Generate test donor data with realistic information
- Select specific campaigns for donation generation
- Configure the number of donations to generate (1-1000)
- Set custom date ranges for when donations should be created
- Automatically creates realistic test donor data
- Generates random donation amounts, billing addresses, and other donation details

## Installation

1. Download or clone this repository to your WordPress plugins directory
2. Run `composer install --no-dev` from the plugin directory
3. Activate the plugin through the WordPress admin
4. Navigate to **Donations > Data Generator** in your WordPress admin

## Usage

1. Go to **Donations > Data Generator** in your WordPress admin
2. Select a campaign from the dropdown
3. Choose how many donations to generate (1-1000)
4. Select a date range:
   - Last 30 Days
   - Last 90 Days
   - Last Year
   - Custom Range (specify start and end dates)
5. Click "Generate Test Data"

## Requirements

- WordPress 4.9+
- PHP 7.4+
- GiveWP 2.8.0+

## Development

This add-on follows the GiveWP domain-driven architecture with:

- `src/Addon/` - Plugin activation, environment checks, and WordPress integration
- `src/TestDonationGenerator/` - Main business logic for generating test donations

### Key Classes

- `AdminSettings` - Handles the admin interface and form rendering
- `DonationGenerator` - Core logic for creating test donations and donors
- `ServiceProvider` - Registers services and hooks with GiveWP

## Generated Data

The add-on generates realistic test data including:

- Random donor names from a predefined list
- Unique email addresses with various domains
- Random phone numbers in US format
- Realistic billing addresses with US states and cities
- Random donation amounts ($5-$500)
- Optional company names and donation comments
- Random transaction IDs and purchase keys

## Security

- Requires `manage_give_settings` capability
- Uses WordPress nonces for CSRF protection
- Validates all input parameters
- Only works in development/testing environments

## Support

This is a development tool and is not officially supported. Use at your own risk and only in testing environments.

## License

GPL-3.0 License

## Setup & Installation
1. Clone this repository to your local
2. Remove the `.git` directory
3. Run `php build.php` from the CLI
4. Run `composer install` from the CLI
5. Run `npm install` from the CLI
6. Update this README (see below for a starting point)

### Asset Compilation
Note: We use [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/).

To compile your CSS & JS assets, run one of the following:

| Command         | Description                                                             |
|:----------------|:------------------------------------------------------------------------|
| `npm run dev`   | Runs a one time build for development. No production files are created. |
| `npm run watch` | Automatically re-builds as changes are made.                            |
| `npm run build` | Builds the minified production files for release.

## Concepts

GiveWP follows a domain-driven model both in core and in add-ons. Each business feature defines
its own domain, including whatever it needs (settings, models, etc.) to do what it does. It's also
important these domains are portable, that is, they are not bound to the plugin and could move to or
from another plugin as needed.

For these reasons, each add-on has two primary directories for handling its logic:
- src/Addon
- src/Domain

### src directory

The src directory handles business domain logic (i.e. a specific feature). The src
directory should have no files in the root, but be a collection of folders. Each folder represents
a distinct domain. Even if there is only one domain for the add-on, it should still live inside a
domain directory.

### src/Domain directory

It is possible for an add-on to have multiple domains, but it will always have at least one. Feel
free to duplicate this directory and make more. This directory is just the starting point for the
initial domain.

### src/Addon directory

This unique domain directory is responsible for the fact that the add-on is a WordPress plugin.
Plugins do things such as activate, upgrade, and uninstall — the logic of which should be handled
there. All GiveWP add-ons also check for compatibility with GiveWP core, and this also is handled
here.

The `src/Addon` directory may reference code in the `src` directory, but not the other way around.
No domain code should reference (and therefore depend on) the `src/Addon` directory. Doing this
keeps the dependency unidirectional.

#### Note for developers

If running `npm run dev` throws an error then check whether the `images` folder exists in your addon directory
under `src/Addon/resources`.

1. If the `images` folder does not exist then create one.
2. If the `images` folder isn't required then remove the code from `webpack.config.js`.

---

DELETE ABOVE THIS LINE WHEN REWRITING README

---

## Introduction

[Write an introduction to what this addon is for]

## Development

### Getting Set Up
1. Clone this repository locally
2. Run `composer install` from the CLI
3. Run `npm install` from the CLI

### Asset Compilation
To compile your CSS & JS assets, run one of the following:
- `npm run dev` — Compiles all assets for development one time
- `npm run watch` — Compiles all assets for development one time and then watches for changes, supporting [BrowserSync](https://laravel-mix.com/docs/5.0/browsersync)
- `npm run hot` — Compiles all assets for development one time and then watches for [hot replacement](https://laravel-mix.com/docs/5.0/hot-module-replacement)
- `npm run dev` — Compiles all assets for production one time

## Testing

This addon includes comprehensive unit and integration tests to ensure reliability and maintainability.

### Running Tests

```bash
# Quick test run
./run-tests.sh

# Run specific test class
./run-tests.sh TestDonationGenerator

# Run specific test method
./run-tests.sh testGenerateDonationsWithValidParameters
```

### Test Coverage

- **Core Functionality**: 15 tests covering donation generation, validation, and data integrity
- **Admin Interface**: 10 tests ensuring proper UI rendering and interaction
- **Service Provider**: 8 tests validating WordPress integration and hooks
- **Integration**: 8 tests covering complete end-to-end workflows
- **Performance**: Tests ensure 100 donations generate in < 30 seconds

### Prerequisites

- WordPress test environment
- GiveWP plugin installed
- PHPUnit (included with GiveWP)

For detailed testing information, see [TESTING.md](TESTING.md).

## Contributing
