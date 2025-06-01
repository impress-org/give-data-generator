<?php

namespace GiveDataGenerator\DataGenerator;

use Give\Framework\Support\Facades\Scripts\ScriptAsset;
use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Donors\Models\Donor;

/**
 * Admin settings for the Data Generator.
 *
 * @package     GiveDataGenerator\DataGenerator
 * @since       1.0.0
 */
class AdminSettings
{
    /**
     * Add admin menu item.
     *
     * @since 1.0.0
     */
    public function addAdminMenu(): void
    {
        // Hook into WordPress 'admin_menu' action with a late priority to ensure this runs last
        add_action('admin_menu', function () {
            add_submenu_page(
                'edit.php?post_type=give_forms',
                __('Data Generator', 'give-data-generator'),
                __('Data Generator', 'give-data-generator'),
                'manage_give_settings',
                'data-generator',
                [$this, 'renderAdminPage']
            );
        }, 999);
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @since 1.0.0
     */
    public function enqueueAdminScripts($hookSuffix): void
    {
        // Only load on our admin page
        if ($hookSuffix !== 'give_forms_page_data-generator') {
            return;
        }

        // Get script asset data from build folder
        $scriptAsset = ScriptAsset::get(GIVE_DATA_GENERATOR_DIR . 'build/admin.asset.php');

        // Enqueue script with dependencies from asset.php
        wp_enqueue_script(
            'data-generator-admin',
            GIVE_DATA_GENERATOR_URL . 'build/admin.js',
            $scriptAsset['dependencies'],
            $scriptAsset['version'],
            true
        );

        // Enqueue WordPress component styles (required for base component styling)
        wp_enqueue_style('wp-components');

        // Enqueue admin common styles for form tables and general admin styling
        wp_enqueue_style('common');

        // Enqueue our bundled custom CSS
        wp_enqueue_style(
            'data-generator-admin',
            GIVE_DATA_GENERATOR_URL . 'build/admin.css',
            ['wp-components', 'common'], // Depend on WordPress styles
            $scriptAsset['version']
        );

        // Set up translations
        wp_set_script_translations('data-generator-admin', 'give-data-generator');

        // Get campaigns and donors data
        $donors = $this->getExistingDonors();

        // Localize script with data and strings
        wp_localize_script('data-generator-admin', 'dataGenerator', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('data_generator_nonce'),
            'campaignNonce' => wp_create_nonce('campaign_generator_nonce'),
            'donationFormNonce' => wp_create_nonce('donation_form_generator_nonce'),
            'subscriptionNonce' => wp_create_nonce('subscription_generator_nonce'),
            'cleanupNonce' => wp_create_nonce('cleanup_nonce'),
            'donors' => $donors,
            'strings' => [
                'errorMessage' => __('An error occurred while generating data.', 'give-data-generator'),
                'processing' => __('Processing...', 'give-data-generator'),
                'success' => __('Success!', 'give-data-generator'),
            ]
        ]);
    }

    /**
     * Get available campaigns.
     *
     * @since 1.0.0
     * @return array
     */
    private function getCampaigns(): array
    {
        try {
            $campaigns = Campaign::query()
                ->where('status', CampaignStatus::ACTIVE()->getValue())
                ->getAll();

            if (!$campaigns) {
                return [];
            }

            return array_map(function($campaign) {
                return [
                    'id' => $campaign->id,
                    'title' => $campaign->title,
                ];
            }, $campaigns);
        } catch (\Exception $e) {
            error_log('Data Generator: Error getting campaigns: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get existing donors.
     *
     * @since 1.0.0
     * @return array
     */
    private function getExistingDonors(): array
    {
        try {
            // Get up to 100 most recent donors to avoid overwhelming the dropdown
            $donors = Donor::query()
                ->orderBy('id', 'DESC')
                ->limit(100)
                ->getAll();

            if (!$donors) {
                return [];
            }

            return array_map(function($donor) {
                return [
                    'id' => $donor->id,
                    'name' => $donor->name,
                    'email' => $donor->email,
                ];
            }, $donors);
        } catch (\Exception $e) {
            error_log('Data Generator: Error getting existing donors: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Render the admin page with React support.
     *
     * @since 1.0.0
     */
    public function renderAdminPage(): void { ?><div id="data-generator-react-root"></div><?php }
}
