=== Give Data Generator ===
Contributors: givewp
Tags: givewp, donations, testing, development, data
Requires at least: 4.9
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Generate test data for GiveWP including donations, donors, and more. For testing and development purposes only.

== Description ==

The Give Data Generator is a development tool that allows you to quickly generate realistic test data for your GiveWP campaigns. This plugin is designed specifically for testing and development environments.

**Features:**

* Generate test donations using the GiveWP Donation Model
* Generate test donor data with realistic information
* Select specific campaigns for donation generation
* Configure the number of donations to generate (1-1000)
* Set custom date ranges for when donations should be created
* Automatically creates realistic test donor data
* Generates random donation amounts, billing addresses, and other donation details

**Requirements:**

* GiveWP 2.8.0 or higher
* PHP 7.4 or higher
* WordPress 4.9 or higher

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/give-data-generator` directory, or install the plugin through the WordPress plugins screen directly.
2. Run `composer install --no-dev` from the plugin directory if installing manually.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Navigate to Donations > Data Generator to use the tool.

== Usage ==

1. Go to Donations > Data Generator in your WordPress admin
2. Select a campaign from the dropdown
3. Choose how many donations to generate (1-1000)
4. Select a date range for the donations
5. Click "Generate Test Data"

== Frequently Asked Questions ==

= Is this safe to use on production sites? =

No, this plugin is designed for testing and development purposes only. Do not use on production sites.

= What data does it generate? =

The plugin generates realistic test data including donor names, email addresses, phone numbers, billing addresses, donation amounts, and other donation details.

= Can I delete the test data? =

Yes, the generated donations can be deleted through the standard GiveWP donations management interface.

== Changelog ==

= 1.0.0 =
* Initial release
* Generate test donations using GiveWP Donation Model
* Campaign selection and date range options
* Realistic test data generation
