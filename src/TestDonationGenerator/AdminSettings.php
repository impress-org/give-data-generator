<?php

namespace GiveFaker\TestDonationGenerator;

use Give\Campaigns\Models\Campaign;

/**
 * Admin settings for the Data Generator.
 *
 * @package     GiveFaker\TestDonationGenerator
 * @since       1.0.0
 */
class AdminSettings
{
    /**
     * Add admin menu item.
     *
     * @since 1.0.0
     */
    public function addAdminMenu()
    {
        add_submenu_page(
            'edit.php?post_type=give_forms',
            __('Data Generator', 'give-data-generator'),
            __('Data Generator', 'give-data-generator'),
            'manage_give_settings',
            'data-generator',
            [$this, 'renderAdminPage']
        );
    }

    /**
     * Render the admin page.
     *
     * @since 1.0.0
     */
    public function renderAdminPage()
    {
        $campaigns = $this->getCampaigns();

        // Ensure $campaigns is always an array
        if (!is_array($campaigns)) {
            $campaigns = [];
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Data Generator', 'give-data-generator'); ?></h1>

            <div class="notice notice-info">
                <p><?php _e('This tool generates test data for GiveWP including donations, donors, and more. Use only for testing purposes.', 'give-data-generator'); ?></p>
            </div>

            <form id="data-generator-form" method="post">
                <?php wp_nonce_field('data_generator_nonce', 'data_generator_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="campaign_id"><?php _e('Campaign', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <select name="campaign_id" id="campaign_id" class="regular-text">
                                <option value=""><?php _e('Select a Campaign', 'give-data-generator'); ?></option>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <option value="<?php echo esc_attr($campaign->id); ?>">
                                        <?php echo esc_html($campaign->title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Choose which campaign the test donations should be associated with.', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donation_count"><?php _e('Number of Donations', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="donation_count" id="donation_count" class="regular-text" min="1" max="1000" value="10" />
                            <p class="description"><?php _e('How many test donations to generate (1-1000).', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="date_range"><?php _e('Date Range', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <select name="date_range" id="date_range" class="regular-text">
                                <option value="last_30_days"><?php _e('Last 30 Days', 'give-data-generator'); ?></option>
                                <option value="last_90_days"><?php _e('Last 90 Days', 'give-data-generator'); ?></option>
                                <option value="last_year"><?php _e('Last Year', 'give-data-generator'); ?></option>
                                <option value="custom"><?php _e('Custom Range', 'give-data-generator'); ?></option>
                            </select>
                            <p class="description"><?php _e('Timeframe within which donations should be created.', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donation_mode"><?php _e('Donation Mode', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <select name="donation_mode" id="donation_mode" class="regular-text">
                                <option value="test"><?php _e('Test Mode', 'give-data-generator'); ?></option>
                                <option value="live"><?php _e('Live Mode', 'give-data-generator'); ?></option>
                            </select>
                            <p class="description"><?php _e('Choose whether donations should be created in test or live mode.', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr id="custom-date-range" style="display: none;">
                        <th scope="row">
                            <label><?php _e('Custom Date Range', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="start_date" id="start_date" class="regular-text" />
                            <span> <?php _e('to', 'give-data-generator'); ?> </span>
                            <input type="date" name="end_date" id="end_date" class="regular-text" />
                            <p class="description"><?php _e('Select the start and end dates for the donation generation.', 'give-data-generator'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" id="generate-donations">
                        <?php _e('Generate Test Data', 'give-data-generator'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin-left: 10px;"></span>
                </p>
            </form>

            <div id="generation-results" style="display: none;">
                <h3><?php _e('Generation Results', 'give-data-generator'); ?></h3>
                <div id="results-content"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Get available campaigns.
     *
     * @since 1.0.0
     * @return array
     */
    private function getCampaigns()
    {
        try {
            $campaigns = Campaign::query()->getAll();

            // Ensure we always return an array
            if (!is_array($campaigns)) {
                $campaigns = [];
            }

            // Debug: Log how many campaigns we found
            error_log('Data Generator: Found ' . count($campaigns) . ' campaigns');

            return $campaigns;
        } catch (\Exception $e) {
            error_log('Data Generator: Error getting campaigns: ' . $e->getMessage());
            return [];
        }
    }
}
