<?php

namespace GiveFaker\TestDonationGenerator;

use Give\Campaigns\Models\Campaign;

/**
 * Admin settings for the Test Donation Generator.
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
            __('Test Donation Generator', 'give-faker'),
            __('Test Donation Generator', 'give-faker'),
            'manage_give_settings',
            'test-donation-generator',
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
        ?>
        <div class="wrap">
            <h1><?php _e('Test Donation Generator', 'give-faker'); ?></h1>

            <div class="notice notice-info">
                <p><?php _e('This tool generates test donations using the GiveWP Donation Model. Use only for testing purposes.', 'give-faker'); ?></p>
            </div>

            <form id="test-donation-generator-form" method="post">
                <?php wp_nonce_field('test_donation_generator_nonce', 'test_donation_generator_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="campaign_id"><?php _e('Campaign', 'give-faker'); ?></label>
                        </th>
                        <td>
                            <select name="campaign_id" id="campaign_id" class="regular-text">
                                <option value=""><?php _e('Select a Campaign', 'give-faker'); ?></option>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <option value="<?php echo esc_attr($campaign->id); ?>">
                                        <?php echo esc_html($campaign->title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Choose which campaign the test donations should be associated with.', 'give-faker'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donation_count"><?php _e('Number of Donations', 'give-faker'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="donation_count" id="donation_count" class="regular-text" min="1" max="1000" value="10" />
                            <p class="description"><?php _e('How many test donations to generate (1-1000).', 'give-faker'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="date_range"><?php _e('Date Range', 'give-faker'); ?></label>
                        </th>
                        <td>
                            <select name="date_range" id="date_range" class="regular-text">
                                <option value="last_30_days"><?php _e('Last 30 Days', 'give-faker'); ?></option>
                                <option value="last_90_days"><?php _e('Last 90 Days', 'give-faker'); ?></option>
                                <option value="last_year"><?php _e('Last Year', 'give-faker'); ?></option>
                                <option value="custom"><?php _e('Custom Range', 'give-faker'); ?></option>
                            </select>
                            <p class="description"><?php _e('Timeframe within which donations should be created.', 'give-faker'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="donation_mode"><?php _e('Donation Mode', 'give-faker'); ?></label>
                        </th>
                        <td>
                            <select name="donation_mode" id="donation_mode" class="regular-text">
                                <option value="test"><?php _e('Test Mode', 'give-faker'); ?></option>
                                <option value="live"><?php _e('Live Mode', 'give-faker'); ?></option>
                            </select>
                            <p class="description"><?php _e('Choose whether donations should be created in test or live mode.', 'give-faker'); ?></p>
                        </td>
                    </tr>

                    <tr id="custom-date-range" style="display: none;">
                        <th scope="row">
                            <label><?php _e('Custom Date Range', 'give-faker'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="start_date" id="start_date" class="regular-text" />
                            <span> <?php _e('to', 'give-faker'); ?> </span>
                            <input type="date" name="end_date" id="end_date" class="regular-text" />
                            <p class="description"><?php _e('Select the start and end dates for the donation generation.', 'give-faker'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" id="generate-donations">
                        <?php _e('Generate Test Donations', 'give-faker'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin-left: 10px;"></span>
                </p>
            </form>

            <div id="generation-results" style="display: none;">
                <h3><?php _e('Generation Results', 'give-faker'); ?></h3>
                <div id="results-content"></div>
            </div>
        </div>

        <script>
        // Ensure testDonationGenerator object is available
        window.testDonationGenerator = window.testDonationGenerator || {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('test_donation_generator_nonce'); ?>'
        };

        jQuery(document).ready(function($) {
            // Show/hide custom date range
            $('#date_range').change(function() {
                if ($(this).val() === 'custom') {
                    $('#custom-date-range').show();
                } else {
                    $('#custom-date-range').hide();
                }
            });

            // Handle form submission
            $('#test-donation-generator-form').submit(function(e) {
                e.preventDefault();

                var $button = $('#generate-donations');
                var $spinner = $('.spinner');
                var $results = $('#generation-results');

                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $results.hide();

                $.ajax({
                    url: testDonationGenerator.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'generate_test_donations',
                        nonce: testDonationGenerator.nonce,
                        campaign_id: $('#campaign_id').val(),
                        donation_count: $('#donation_count').val(),
                        date_range: $('#date_range').val(),
                        donation_mode: $('#donation_mode').val(),
                        start_date: $('#start_date').val(),
                        end_date: $('#end_date').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#results-content').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            $results.show();
                        } else {
                            $('#results-content').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                            $results.show();
                        }
                    },
                    error: function() {
                        $('#results-content').html('<div class="notice notice-error"><p><?php _e("An error occurred while generating donations.", "give-faker"); ?></p></div>');
                        $results.show();
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
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

            // Debug: Log how many campaigns we found
            error_log('Test Donation Generator: Found ' . count($campaigns) . ' campaigns');

            return $campaigns;
        } catch (\Exception $e) {
            error_log('Test Donation Generator: Error getting campaigns: ' . $e->getMessage());
            return [];
        }
    }
}
