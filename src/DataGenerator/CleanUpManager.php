<?php

namespace GiveDataGenerator\DataGenerator;

use Give\Donations\Models\Donation;
use Give\Subscriptions\Models\Subscription;
use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignPageStatus;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Donations\ValueObjects\DonationMode;
use Give\Subscriptions\ValueObjects\SubscriptionMode;

/**
 * Clean Up Manager for removing test data.
 *
 * @package     GiveDataGenerator\DataGenerator
 * @since       1.0.0
 */
class CleanUpManager
{
    /**
     * Handle AJAX request for cleanup operations.
     *
     * @since 1.0.0
     */
    public function handleAjaxRequest(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cleanup_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'give-data-generator')]);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_give_settings')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'give-data-generator')]);
            return;
        }

        $actionType = sanitize_text_field($_POST['action_type'] ?? '');

        switch ($actionType) {
            case 'delete_test_donations':
                $result = $this->deleteTestDonations();
                break;
            case 'delete_test_subscriptions':
                $result = $this->deleteTestSubscriptions();
                break;
            case 'archive_campaigns':
                $result = $this->archiveCampaigns();
                break;
            default:
                wp_send_json_error(['message' => __('Invalid action type.', 'give-data-generator')]);
                return;
        }

        wp_send_json_success($result);
    }

    /**
     * Delete all test mode donations.
     *
     * @since 1.0.0
     * @return array
     */
    private function deleteTestDonations(): array
    {
        try {
            // Get test mode donations - use the aliased column from attachMeta
            $donations = Donation::query()
                ->where('give_donationmeta_attach_meta_mode.meta_value', 'test')
                ->getAll();

            $deletedCount = 0;
            $errors = [];

            // Handle null result
            if ($donations === null) {
                $donations = [];
            }

            // Log the number of donations found
            error_log('CleanUpManager: Found ' . count($donations) . ' test donations to delete');

            foreach ($donations as $donation) {
                try {
                    // Use GiveWP's built-in deletion method which handles all related data
                    $donation->delete();
                    if ($donation->donor) {
                        $donation->donor->delete();
                    }
                    $deletedCount++;
                } catch (\Exception $e) {
                    $errors[] = sprintf(
                        __('Error deleting donation ID %d: %s', 'give-data-generator'),
                        $donation->id,
                        $e->getMessage()
                    );
                    error_log('CleanUpManager: Error deleting donation ID ' . $donation->id . ': ' . $e->getMessage());
                }
            }

            return [
                'deleted_count' => $deletedCount,
                'message' => sprintf(__('%d test donations deleted successfully.', 'give-data-generator'), $deletedCount),
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            error_log('CleanUpManager: Fatal error in deleteTestDonations: ' . $e->getMessage());
            return [
                'deleted_count' => 0,
                'message' => __('Error occurred while deleting test donations.', 'give-data-generator'),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Delete all test mode subscriptions.
     *
     * @since 1.0.0
     * @return array
     */
    private function deleteTestSubscriptions(): array
    {
        try {
            // Get test mode subscriptions using the column approach (subscriptions table has mode column)
            $subscriptions = Subscription::query()
                ->where('payment_mode', 'test')
                ->getAll();

            $deletedCount = 0;
            $errors = [];

            // Handle null result
            if ($subscriptions === null) {
                $subscriptions = [];
            }

            // Log the number of subscriptions found
            error_log('CleanUpManager: Found ' . count($subscriptions) . ' test subscriptions to delete');

            foreach ($subscriptions as $subscription) {
                try {
                    // Use GiveWP's built-in deletion method which handles all related data
                    $subscription->delete();
                    $deletedCount++;
                } catch (\Exception $e) {
                    $errors[] = sprintf(
                        __('Error deleting subscription ID %d: %s', 'give-data-generator'),
                        $subscription->id,
                        $e->getMessage()
                    );
                    error_log('CleanUpManager: Error deleting subscription ID ' . $subscription->id . ': ' . $e->getMessage());
                }
            }

            return [
                'deleted_count' => $deletedCount,
                'message' => sprintf(__('%d test subscriptions deleted successfully.', 'give-data-generator'), $deletedCount),
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            error_log('CleanUpManager: Fatal error in deleteTestSubscriptions: ' . $e->getMessage());
            return [
                'deleted_count' => 0,
                'message' => __('Error occurred while deleting test subscriptions.', 'give-data-generator'),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Archive all campaigns.
     *
     * @since 1.0.0
     * @return array
     */
    private function archiveCampaigns(): array
    {
        try {
            // Get all active campaigns - try multiple approaches to ensure compatibility
            $campaigns = Campaign::query()
                ->where('status', CampaignStatus::ACTIVE())
                ->getAll();

            // Fallback to string-based query if enum query doesn't work
            if (!is_array($campaigns) || count($campaigns) === 0) {
                $campaigns = Campaign::query()
                    ->where('status', 'active')
                    ->getAll();
            }

            // Final fallback using getValue() method
            if (!is_array($campaigns) || count($campaigns) === 0) {
                $activeStatus = CampaignStatus::ACTIVE();
                $campaigns = Campaign::query()
                    ->where('status', $activeStatus->getValue())
                    ->getAll();
            }

            $archivedCount = 0;
            $errors = [];

            // Ensure we have an array to work with
            if (!is_array($campaigns)) {
                $campaigns = [];
            }

            foreach ($campaigns as $campaign) {
                try {
                    // Update campaign status to archived
                    $campaign->status = CampaignStatus::ARCHIVED();
                    $campaign->save();

                    if ($page = $campaign->page()) {
                        $page->status = CampaignPageStatus::TRASH();
                        $page->save();

                        wp_trash_post($page->id);
                    }

                    $archivedCount++;
                } catch (\Exception $e) {
                    $errors[] = sprintf(
                        __('Error archiving campaign ID %d: %s', 'give-data-generator'),
                        $campaign->id,
                        $e->getMessage()
                    );
                    error_log('CleanUpManager: Error archiving campaign ID ' . $campaign->id . ': ' . $e->getMessage());
                }
            }

            return [
                'archived_count' => $archivedCount,
                'message' => sprintf(__('%d campaigns archived successfully.', 'give-data-generator'), $archivedCount),
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            error_log('CleanUpManager: Fatal error in archiveCampaigns: ' . $e->getMessage());
            return [
                'archived_count' => 0,
                'message' => __('Error occurred while archiving campaigns.', 'give-data-generator'),
                'errors' => [$e->getMessage()]
            ];
        }
    }
}
