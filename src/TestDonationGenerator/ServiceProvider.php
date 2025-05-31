<?php

namespace GiveFaker\TestDonationGenerator;

use Give\Helpers\Hooks;
use Give\ServiceProviders\ServiceProvider as ServiceProviderInterface;
use GiveFaker\TestDonationGenerator\AdminSettings;
use GiveFaker\TestDonationGenerator\DonationGenerator;

/**
 * Service provider for the Test Donation Generator domain.
 *
 * @package     GiveFaker\TestDonationGenerator
 * @since       1.0.0
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        give()->singleton(DonationGenerator::class);
        give()->singleton(AdminSettings::class);
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        // Register admin settings
        Hooks::addAction('admin_menu', AdminSettings::class, 'addAdminMenu');

        // Handle AJAX requests
        Hooks::addAction('wp_ajax_generate_test_donations', DonationGenerator::class, 'handleAjaxRequest');

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @since 1.0.0
     */
    public function enqueueAdminScripts($hook_suffix)
    {
        // The hook suffix for submenus under edit.php?post_type=give_forms is: give_forms_page_{menu_slug}
        if ($hook_suffix !== 'give_forms_page_test-donation-generator') {
            return;
        }

        wp_enqueue_script('jquery');

        // Enqueue our admin script
        wp_enqueue_script(
            'test-donation-generator-admin',
            GIVE_FAKER_URL . 'build/admin.js',
            ['jquery'],
            GIVE_FAKER_VERSION,
            true
        );

        // Localize script with data and strings
        wp_localize_script('test-donation-generator-admin', 'testDonationGenerator', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('test_donation_generator_nonce'),
            'strings' => [
                'errorMessage' => __('An error occurred while generating donations.', 'give-faker'),
                'processing' => __('Processing...', 'give-faker'),
                'success' => __('Success!', 'give-faker'),
            ]
        ]);
    }
}
