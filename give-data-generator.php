<?php
namespace GiveDataGenerator;

use GiveDataGenerator\Addon\Activation;
use GiveDataGenerator\Addon\Environment;
use GiveDataGenerator\Addon\ServiceProvider as AddonServiceProvider;
use GiveDataGenerator\DataGenerator\ServiceProvider as DataGeneratorServiceProvider;

/**
 * Plugin Name:         Give Data Generator
 * Plugin URI:          https://givewp.com/addons/data-generator/
 * Description:         Generate test data for GiveWP including donations, donors, and more
 * Version:             1.0.0
 * Requires at least:   4.9
 * Requires PHP:        7.4
 * Author:              GiveWP
 * Author URI:          https://givewp.com/
 * Text Domain:         give-data-generator
 * Domain Path:         /languages
 */
defined('ABSPATH') or exit;

// Add-on name
define('GIVE_DATA_GENERATOR_NAME', 'Give Data Generator');

// Versions
define('GIVE_DATA_GENERATOR_VERSION', '1.0.0');
define('GIVE_DATA_GENERATOR_MIN_GIVE_VERSION', '2.8.0');

// Add-on paths
define('GIVE_DATA_GENERATOR_FILE', __FILE__);
define('GIVE_DATA_GENERATOR_DIR', plugin_dir_path(GIVE_DATA_GENERATOR_FILE));
define('GIVE_DATA_GENERATOR_URL', plugin_dir_url(GIVE_DATA_GENERATOR_FILE));
define('GIVE_DATA_GENERATOR_BASENAME', plugin_basename(GIVE_DATA_GENERATOR_FILE));

require 'vendor/autoload.php';

// Activate add-on hook.
register_activation_hook(GIVE_DATA_GENERATOR_FILE, [Activation::class, 'activateAddon']);

// Deactivate add-on hook.
register_deactivation_hook(GIVE_DATA_GENERATOR_FILE, [Activation::class, 'deactivateAddon']);

// Uninstall hook.
register_uninstall_hook(GIVE_DATA_GENERATOR_FILE, [Activation::class, 'uninstallAddon']);

// Register the add-on service provider with the GiveWP core.
add_action(
    'before_give_init',
    function () {
        // Check Give min required version.
        if (Environment::giveMinRequiredVersionCheck()) {
            give()->registerServiceProvider(AddonServiceProvider::class);
            give()->registerServiceProvider(DataGeneratorServiceProvider::class);
        }
    }
);

// Check to make sure GiveWP core is installed and compatible with this add-on.
add_action('admin_init', [Environment::class, 'checkEnvironment']);
