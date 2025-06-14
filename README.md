# GiveWP - Data Generator

A WordPress plugin that generates test data for GiveWP including donations, donors, campaigns, donation forms, and more. This add-on is designed for testing and development purposes only.

## Features

- **Generate test campaigns** with customizable goals, colors, and descriptions
- **Generate test donations** using the GiveWP Donation Model
- **Generate test donation forms** for specific campaigns with various designs and settings
- **Generate test subscriptions** with different billing periods and statuses
- Generate test donor data with realistic information
- Choose between creating new donors, using existing donors, or a mix of both
- Select specific campaigns for donation generation
- Configure the number of donations to generate (1-1000)
- Set custom date ranges for when donations should be created
- Automatically creates realistic test donor data
- Generates random donation amounts, billing addresses, and other donation details
- **Clean up tools** to remove test data when needed

## Installation

1. Download or clone this repository to your WordPress plugins directory
2. Run `composer install --no-dev` from the plugin directory
3. Activate the plugin through the WordPress admin
4. Navigate to **Donations > Data Generator** in your WordPress admin

## Usage

### Campaigns Tab
Generate test campaigns with:
- Customizable goal types (amount, donations, donors, subscriptions)
- Random color schemes or themed colors
- Campaign descriptions and images
- Various campaign durations
- Automatic form creation

### Donations Tab
1. Go to **Donations > Data Generator** in your WordPress admin
2. Select a campaign from the dropdown
3. Choose your donor creation method:
   - **Create New Donors** - Generates unique new donors for each donation
   - **Use Existing Donors** - Randomly selects from existing donors in your database
   - **Mix of New and Existing** - Randomly creates new donors or uses existing ones (50/50 split)
   - **Select Specific Donor** - Choose a specific existing donor to use for all generated donations
4. If you selected "Select Specific Donor", choose the donor from the dropdown list
5. Choose how many donations to generate (1-1000)
6. Select a date range:
   - Last 30 Days
   - Last 90 Days
   - Last Year
   - Custom Range (specify start and end dates)
7. Click "Generate Test Data"

### Donation Forms Tab
Generate test donation forms for specific campaigns with:
- **Campaign Selection** - Choose which campaign to associate forms with
- **Form Count** - Generate 1-20 forms per campaign
- **Form Status** - Published, Draft, or Private
- **Goal Settings** - Enable/disable goals with various types:
  - Inherit from Campaign
  - Custom Amount
  - Number of Donations
  - Number of Donors
- **Form Designs** - Randomly assign or use specific designs:
  - Multi-step Form Design
  - Classic Form Design
  - Two Panel Form Design
- **Campaign Colors** - Inherit primary/secondary colors from campaign
- **Title Prefix** - Optional custom prefix for form titles

### Subscriptions Tab
Generate test subscriptions with:
- Various billing periods (daily, weekly, monthly, quarterly, yearly)
- Different subscription statuses
- Custom frequency and installment settings
- Renewal payment generation

### Clean Up Tab
Remove test data with:
- Delete all test mode donations
- Delete all test mode subscriptions
- Archive all active campaigns

## Requirements

- WordPress 4.9+
- PHP 7.4+
- GiveWP 2.8.0+

## Development

This add-on follows the GiveWP domain-driven architecture with:

- `src/Addon/` - Plugin activation, environment checks, and WordPress integration
- `src/DataGenerator/` - Main business logic for generating test data

### Key Classes

- `AdminSettings` - Handles the admin interface and form rendering
- `DonationGenerator` - Core logic for creating test donations and donors
- `CampaignGenerator` - Logic for creating test campaigns
- `DonationFormGenerator` - Logic for creating test donation forms for campaigns
- `SubscriptionGenerator` - Logic for creating test subscriptions
- `CleanUpManager` - Handles removal of test data
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
