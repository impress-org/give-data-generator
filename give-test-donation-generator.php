<?php
namespace GiveFaker;

use GiveFaker\Addon\Activation;
use GiveFaker\Addon\Environment;
use GiveFaker\Addon\ServiceProvider as AddonServiceProvider;
use GiveFaker\TestDonationGenerator\ServiceProvider as TestDonationGeneratorServiceProvider;

/**
 * Plugin Name:         Give Test Donation Generator
 * Plugin URI:          https://givewp.com/addons/test-donation-generator/
 * Description:         Generate test donations for GiveWP using the Donation Model
 * Version:             1.0.0
 * Requires at least:   4.9
 * Requires PHP:        7.4
 * Author:              GiveWP
 * Author URI:          https://givewp.com/
 * Text Domain:         give-faker
 * Domain Path:         /languages
 */
defined('ABSPATH') or exit;

// Add-on name
define('GIVE_FAKER_NAME', 'Give Test Donation Generator');

// Versions
define('GIVE_FAKER_VERSION', '1.0.0');
define('GIVE_FAKER_MIN_GIVE_VERSION', '2.8.0');

// Add-on paths
define('GIVE_FAKER_FILE', __FILE__);
define('GIVE_FAKER_DIR', plugin_dir_path(GIVE_FAKER_FILE));
define('GIVE_FAKER_URL', plugin_dir_url(GIVE_FAKER_FILE));
define('GIVE_FAKER_BASENAME', plugin_basename(GIVE_FAKER_FILE));

require 'vendor/autoload.php';

// Activate add-on hook.
register_activation_hook(GIVE_FAKER_FILE, [Activation::class, 'activateAddon']);

// Deactivate add-on hook.
register_deactivation_hook(GIVE_FAKER_FILE, [Activation::class, 'deactivateAddon']);

// Uninstall add-on hook.
register_uninstall_hook(GIVE_FAKER_FILE, [Activation::class, 'uninstallAddon']);

// Register the add-on service provider with the GiveWP core.
add_action(
    'before_give_init',
    function () {
        // Check Give min required version.
        if (Environment::giveMinRequiredVersionCheck()) {
            give()->registerServiceProvider(AddonServiceProvider::class);
            give()->registerServiceProvider(TestDonationGeneratorServiceProvider::class);
        }
    }
);

// Check to make sure GiveWP core is installed and compatible with this add-on.
add_action('admin_init', [Environment::class, 'checkEnvironment']);
