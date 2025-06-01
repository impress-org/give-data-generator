<?php

namespace GiveDataGenerator\DataGenerator;

use Give\Helpers\Hooks;
use Give\ServiceProviders\ServiceProvider as ServiceProviderInterface;
use GiveDataGenerator\DataGenerator\AdminSettings;
use GiveDataGenerator\DataGenerator\DonationGenerator;
use GiveDataGenerator\DataGenerator\CampaignGenerator;

/**
 * Service provider for the Data Generator domain.
 *
 * @package     GiveDataGenerator\DataGenerator
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
        give()->singleton(CampaignGenerator::class);
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
        Hooks::addAction('wp_ajax_generate_test_campaigns', CampaignGenerator::class, 'handleAjaxRequest');

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
        if ($hook_suffix !== 'give_forms_page_data-generator') {
            return;
        }

        wp_enqueue_script('jquery');

        // Enqueue our admin script
        wp_enqueue_script(
            'data-generator-admin',
            GIVE_DATA_GENERATOR_URL . 'build/admin.js',
            ['jquery'],
            GIVE_DATA_GENERATOR_VERSION,
            true
        );

        // Localize script with data and strings
        wp_localize_script('data-generator-admin', 'dataGenerator', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('data_generator_nonce'),
            'campaignNonce' => wp_create_nonce('campaign_generator_nonce'),
            'strings' => [
                'errorMessage' => __('An error occurred while generating data.', 'give-data-generator'),
                'processing' => __('Processing...', 'give-data-generator'),
                'success' => __('Success!', 'give-data-generator'),
            ]
        ]);
    }
}
