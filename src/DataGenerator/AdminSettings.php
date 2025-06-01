<?php

namespace GiveDataGenerator\DataGenerator;

use Give\Campaigns\Models\Campaign;
use Give\Donations\ValueObjects\DonationStatus;

/**
 * Admin settings for the Data Generator.
 *
 * @package     GiveDataGenerator\DataGenerator
 * @since       1.0.0
 */
class AdminSettings
{
    /**
     * Available tabs for the admin interface.
     *
     * @since 1.0.0
     * @var array
     */
    private array $tabs = [
        'donations' => 'Donations',
        'campaigns' => 'Campaigns',
    ];

    /**
     * Current active tab.
     *
     * @since 1.0.0
     * @var string
     */
    private string $currentTab;

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
     * Render the admin page with tabs.
     *
     * @since 1.0.0
     */
    public function renderAdminPage(): void
    {
        $this->currentTab = $this->getCurrentTab();
        ?>
        <div class="wrap">
            <h1><?php _e('Data Generator', 'give-data-generator'); ?></h1>

            <div class="notice notice-info">
                <p><?php _e('This tool generates test data for GiveWP including donations, donors, and more. Use only for testing purposes.', 'give-data-generator'); ?></p>
            </div>

            <?php $this->renderTabs(); ?>

            <div class="tab-content">
                <?php $this->renderCurrentTabContent(); ?>
            </div>

            <?php $this->renderTabStyles(); ?>
            <?php $this->renderTabScripts(); ?>
        </div>
        <?php
    }

    /**
     * Get the current active tab.
     *
     * @since 1.0.0
     * @return string
     */
    private function getCurrentTab(): string
    {
        $tab = $_GET['tab'] ?? 'donations';

        return array_key_exists($tab, $this->tabs) ? $tab : 'donations';
    }

    /**
     * Render the tab navigation.
     *
     * @since 1.0.0
     */
    private function renderTabs(): void
    {
        ?>
        <nav class="nav-tab-wrapper">
            <?php foreach ($this->tabs as $tabKey => $tabLabel): ?>
                <a href="<?php echo esc_url($this->getTabUrl($tabKey)); ?>"
                   class="nav-tab <?php echo $this->currentTab === $tabKey ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html(__($tabLabel, 'give-data-generator')); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    /**
     * Get the URL for a specific tab.
     *
     * @since 1.0.0
     * @param string $tab The tab key
     * @return string
     */
    private function getTabUrl(string $tab): string
    {
        return add_query_arg([
            'post_type' => 'give_forms',
            'page' => 'data-generator',
            'tab' => $tab,
        ], admin_url('edit.php'));
    }

    /**
     * Render the content for the current active tab.
     *
     * @since 1.0.0
     */
    private function renderCurrentTabContent(): void
    {
        switch ($this->currentTab) {
            case 'donations':
                $this->renderDonationsTab();
                break;
            case 'campaigns':
                $this->renderCampaignsTab();
                break;
            default:
                $this->renderDonationsTab();
                break;
        }
    }

    /**
     * Render the donations tab content.
     *
     * @since 1.0.0
     */
    private function renderDonationsTab(): void
    {
        $campaigns = $this->getCampaigns();

        // Ensure $campaigns is always an array
        if (!is_array($campaigns)) {
            $campaigns = [];
        }
        ?>
        <div class="tab-panel" id="donations-panel">
            <form id="donation-generator-form" method="post">
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

                    <tr>
                        <th scope="row">
                            <label for="donation_status"><?php _e('Donation Status', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <select name="donation_status" id="donation_status" class="regular-text">
                                <option value="<?php echo DonationStatus::COMPLETE()->getValue(); ?>"><?php _e('Complete', 'give-data-generator'); ?></option>
                                <option value="<?php echo DonationStatus::PENDING()->getValue(); ?>"><?php _e('Pending', 'give-data-generator'); ?></option>
                                <option value="<?php echo DonationStatus::PROCESSING()->getValue(); ?>"><?php _e('Processing', 'give-data-generator'); ?></option>
                                <option value="<?php echo DonationStatus::REFUNDED()->getValue(); ?>"><?php _e('Refunded', 'give-data-generator'); ?></option>
                                <option value="<?php echo DonationStatus::FAILED()->getValue(); ?>"><?php _e('Failed', 'give-data-generator'); ?></option>
                                <option value="<?php echo DonationStatus::CANCELLED()->getValue(); ?>"><?php _e('Cancelled', 'give-data-generator'); ?></option>
                                <option value="<?php echo DonationStatus::ABANDONED()->getValue(); ?>"><?php _e('Abandoned', 'give-data-generator'); ?></option>
                                <option value="<?php echo DonationStatus::PREAPPROVAL()->getValue(); ?>"><?php _e('Preapproval', 'give-data-generator'); ?></option>
                                <option value="<?php echo DonationStatus::REVOKED()->getValue(); ?>"><?php _e('Revoked', 'give-data-generator'); ?></option>
                                <option value="random"><?php _e('Random', 'give-data-generator'); ?></option>
                            </select>
                            <p class="description"><?php _e('Status for the generated donations. Select "Random" to use a mix of statuses.', 'give-data-generator'); ?></p>
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
     * Render the campaigns tab content.
     *
     * @since 1.0.0
     */
    private function renderCampaignsTab(): void
    {
        ?>
        <div class="tab-panel" id="campaigns-panel">
            <form id="campaign-generator-form" method="post">
                <?php wp_nonce_field('campaign_generator_nonce', 'campaign_generator_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="campaign_count"><?php _e('Number of Campaigns', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="campaign_count" id="campaign_count" class="regular-text" min="1" max="50" value="5" />
                            <p class="description"><?php _e('How many test campaigns to generate (1-50).', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="campaign_status"><?php _e('Campaign Status', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <select name="campaign_status" id="campaign_status" class="regular-text">
                                <option value="active"><?php _e('Active', 'give-data-generator'); ?></option>
                                <option value="draft"><?php _e('Draft', 'give-data-generator'); ?></option>
                                <option value="inactive"><?php _e('Inactive', 'give-data-generator'); ?></option>
                                <option value="pending"><?php _e('Pending', 'give-data-generator'); ?></option>
                                <option value="archived"><?php _e('Archived', 'give-data-generator'); ?></option>
                            </select>
                            <p class="description"><?php _e('Status for the generated campaigns.', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="goal_type"><?php _e('Goal Type', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <select name="goal_type" id="goal_type" class="regular-text">
                                <option value="amount"><?php _e('Amount', 'give-data-generator'); ?></option>
                                <option value="donations"><?php _e('Number of Donations', 'give-data-generator'); ?></option>
                                <option value="donors"><?php _e('Number of Donors', 'give-data-generator'); ?></option>
                                <option value="amountFromSubscriptions"><?php _e('Amount from Subscriptions', 'give-data-generator'); ?></option>
                                <option value="subscriptions"><?php _e('Number of Subscriptions', 'give-data-generator'); ?></option>
                                <option value="donorsFromSubscriptions"><?php _e('Donors from Subscriptions', 'give-data-generator'); ?></option>
                            </select>
                            <p class="description"><?php _e('Type of goal for the campaigns.', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="goal_amount_min"><?php _e('Goal Amount Range', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="goal_amount_min" id="goal_amount_min" class="regular-text" min="100" value="1000" />
                            <span> <?php _e('to', 'give-data-generator'); ?> </span>
                            <input type="number" name="goal_amount_max" id="goal_amount_max" class="regular-text" min="100" value="10000" />
                            <p class="description"><?php _e('Random goal amounts will be generated within this range.', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="primary_color"><?php _e('Primary Color Range', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <select name="color_scheme" id="color_scheme" class="regular-text">
                                <option value="random"><?php _e('Random Colors', 'give-data-generator'); ?></option>
                                <option value="blue_theme"><?php _e('Blue Theme', 'give-data-generator'); ?></option>
                                <option value="green_theme"><?php _e('Green Theme', 'give-data-generator'); ?></option>
                                <option value="red_theme"><?php _e('Red Theme', 'give-data-generator'); ?></option>
                                <option value="purple_theme"><?php _e('Purple Theme', 'give-data-generator'); ?></option>
                                <option value="orange_theme"><?php _e('Orange Theme', 'give-data-generator'); ?></option>
                            </select>
                            <p class="description"><?php _e('Color scheme for generated campaigns.', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="include_descriptions"><?php _e('Include Descriptions', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_short_desc" id="include_short_desc" value="1" checked />
                                <?php _e('Generate short descriptions', 'give-data-generator'); ?>
                            </label>
                            <br />
                            <label>
                                <input type="checkbox" name="include_long_desc" id="include_long_desc" value="1" checked />
                                <?php _e('Generate long descriptions', 'give-data-generator'); ?>
                            </label>
                            <p class="description"><?php _e('Whether to generate descriptive content for campaigns.', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="campaign_duration"><?php _e('Campaign Duration', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <select name="campaign_duration" id="campaign_duration" class="regular-text">
                                <option value="ongoing"><?php _e('Ongoing (No End Date)', 'give-data-generator'); ?></option>
                                <option value="30_days"><?php _e('30 Days', 'give-data-generator'); ?></option>
                                <option value="60_days"><?php _e('60 Days', 'give-data-generator'); ?></option>
                                <option value="90_days"><?php _e('90 Days', 'give-data-generator'); ?></option>
                                <option value="6_months"><?php _e('6 Months', 'give-data-generator'); ?></option>
                                <option value="1_year"><?php _e('1 Year', 'give-data-generator'); ?></option>
                            </select>
                            <p class="description"><?php _e('Duration for the generated campaigns.', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="create_forms"><?php _e('Create Associated Forms', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="create_forms" id="create_forms" value="1" checked />
                                <?php _e('Create default donation forms for each campaign', 'give-data-generator'); ?>
                            </label>
                            <p class="description"><?php _e('Automatically create and associate donation forms with each campaign.', 'give-data-generator'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="campaign_title_prefix"><?php _e('Title Prefix', 'give-data-generator'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="campaign_title_prefix" id="campaign_title_prefix" class="regular-text" value="Test Campaign" />
                            <p class="description"><?php _e('Prefix for generated campaign titles (e.g., "Test Campaign - Save the Whales").', 'give-data-generator'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary" id="generate-campaigns">
                        <?php _e('Generate Test Campaigns', 'give-data-generator'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin-left: 10px;"></span>
                </p>
            </form>

            <div id="campaign-generation-results" style="display: none;">
                <h3><?php _e('Generation Results', 'give-data-generator'); ?></h3>
                <div id="campaign-results-content"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render tab styles.
     *
     * @since 1.0.0
     */
    private function renderTabStyles(): void
    {
        ?>
        <style>
            .tab-content {
                margin-top: 20px;
            }

            .tab-panel {
                display: block;
            }

            .nav-tab-wrapper {
                border-bottom: 1px solid #c3c4c7;
                margin-bottom: 0;
            }

            .nav-tab {
                margin-bottom: -1px;
            }
        </style>
        <?php
    }

    /**
     * Render tab scripts.
     *
     * @since 1.0.0
     */
    private function renderTabScripts(): void
    {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Handle date range selection for donations tab
                const dateRangeSelect = document.getElementById('date_range');
                const customDateRange = document.getElementById('custom-date-range');

                if (dateRangeSelect && customDateRange) {
                    dateRangeSelect.addEventListener('change', function() {
                        if (this.value === 'custom') {
                            customDateRange.style.display = 'table-row';
                        } else {
                            customDateRange.style.display = 'none';
                        }
                    });
                }

                // Handle goal type changes for campaigns tab
                const goalTypeSelect = document.getElementById('goal_type');
                const goalAmountMin = document.getElementById('goal_amount_min');
                const goalAmountMax = document.getElementById('goal_amount_max');

                if (goalTypeSelect && goalAmountMin && goalAmountMax) {
                    goalTypeSelect.addEventListener('change', function() {
                        const isAmountType = this.value === 'amount' || this.value === 'amountFromSubscriptions';

                        if (isAmountType) {
                            goalAmountMin.placeholder = 'e.g., 1000';
                            goalAmountMax.placeholder = 'e.g., 10000';
                            goalAmountMin.parentNode.querySelector('.description').textContent =
                                'Random goal amounts will be generated within this range.';
                        } else {
                            goalAmountMin.placeholder = 'e.g., 10';
                            goalAmountMax.placeholder = 'e.g., 100';
                            goalAmountMin.parentNode.querySelector('.description').textContent =
                                'Random goal counts will be generated within this range.';
                        }
                    });

                    // Trigger change event on page load
                    goalTypeSelect.dispatchEvent(new Event('change'));
                }
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
    private function getCampaigns(): array
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
