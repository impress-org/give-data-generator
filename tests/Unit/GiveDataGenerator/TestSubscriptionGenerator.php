<?php

namespace GiveDataGenerator\Tests\Unit\GiveDataGenerator;

use DateTime;
use Exception;
use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignGoalType;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Campaigns\ValueObjects\CampaignType;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionPeriod;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use Give\Donations\Models\Donation;
use Give\Donors\Models\Donor;
use Give\Tests\TestCase;
use Give\Tests\TestTraits\RefreshDatabase;
use GiveDataGenerator\DataGenerator\SubscriptionGenerator;

class TestSubscriptionGenerator extends TestCase
{
    use RefreshDatabase;

    /**
     * @var SubscriptionGenerator
     */
    private $generator;

    /**
     * @var Campaign
     */
    private $testCampaign;

    /**
     * Set up test environment.
     *
     * @since 1.0.0
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->generator = new SubscriptionGenerator();

        // Create a test campaign
        $this->testCampaign = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Test Campaign',
            'shortDescription' => 'Test campaign description',
            'longDescription' => 'Test campaign long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 100000, // $1000
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);
    }

    /**
     * Test generating subscriptions with valid parameters.
     *
     * @since 1.0.0
     */
    public function testGenerateSubscriptionsWithValidParameters()
    {
        $subscriptionCount = 5;
        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            $subscriptionCount,
            'last_30_days',
            'test',
            'active',
            'month',
            1,
            0
        );

        $this->assertEquals($subscriptionCount, $generated);

        // Verify subscriptions were created
        $subscriptions = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->getAll();

        $this->assertCount($subscriptionCount, $subscriptions);
    }

    /**
     * Test generating subscriptions with test mode.
     *
     * @since 1.0.0
     */
    public function testGenerateSubscriptionsInTestMode()
    {
        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            3,
            'last_30_days',
            'test',
            'active',
            'month',
            1,
            0
        );

        $this->assertEquals(3, $generated);

        $subscriptions = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->getAll();

        foreach ($subscriptions as $subscription) {
            $this->assertEquals('test', $subscription->mode->getValue());
            $this->assertEquals('active', $subscription->status->getValue());
        }
    }

    /**
     * Test generating subscriptions with live mode.
     *
     * @since 1.0.0
     */
    public function testGenerateSubscriptionsInLiveMode()
    {
        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            2,
            'last_30_days',
            'live',
            'active',
            'month',
            1,
            0
        );

        $this->assertEquals(2, $generated);

        $subscriptions = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->getAll();

        foreach ($subscriptions as $subscription) {
            $this->assertEquals('live', $subscription->mode->getValue());
        }
    }

    /**
     * Test generating subscriptions with custom date range.
     *
     * @since 1.0.0
     */
    public function testGenerateSubscriptionsWithCustomDateRange()
    {
        $startDate = '2024-01-01';
        $endDate = '2024-01-31';

        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            3,
            'custom',
            'test',
            'active',
            'month',
            1,
            0,
            $startDate,
            $endDate
        );

        $this->assertEquals(3, $generated);

        $subscriptions = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->getAll();

        foreach ($subscriptions as $subscription) {
            $createdAt = new DateTime($subscription->createdAt);
            $this->assertGreaterThanOrEqual(new DateTime($startDate), $createdAt);
            $this->assertLessThanOrEqual(new DateTime($endDate), $createdAt);
        }
    }

    /**
     * Test generating subscriptions with different billing periods.
     *
     * @since 1.0.0
     */
    public function testGenerateSubscriptionsWithDifferentPeriods()
    {
        // Test monthly subscriptions
        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            1,
            'last_30_days',
            'test',
            'active',
            'month',
            1,
            0
        );

        $this->assertEquals(1, $generated);

        $subscription = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->get();

        $this->assertEquals('month', $subscription->period->getValue());
        $this->assertEquals(1, $subscription->frequency);

        // Test yearly subscriptions
        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            1,
            'last_30_days',
            'test',
            'active',
            'year',
            1,
            0
        );

        $this->assertEquals(1, $generated);
    }

    /**
     * Test that subscriptions have realistic amounts.
     *
     * @since 1.0.0
     */
    public function testSubscriptionsHaveRealisticAmounts()
    {
        $this->generator->generateSubscriptions(
            $this->testCampaign,
            10,
            'last_30_days',
            'test',
            'active',
            'month',
            1,
            0
        );

        $subscriptions = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->getAll();

        foreach ($subscriptions as $subscription) {
            $amount = $subscription->amount->getMinorAmount() / 100; // Convert cents to dollars
            $this->assertGreaterThanOrEqual(5, $amount);
            $this->assertLessThanOrEqual(1000, $amount);
        }
    }

    /**
     * Test that initial donations are created for subscriptions.
     *
     * @since 1.0.0
     */
    public function testInitialDonationsAreCreated()
    {
        $subscriptionCount = 3;
        $this->generator->generateSubscriptions(
            $this->testCampaign,
            $subscriptionCount,
            'last_30_days',
            'test',
            'active',
            'month',
            1,
            0
        );

        // Get all subscriptions for this campaign
        $subscriptions = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->getAll();

        $this->assertCount($subscriptionCount, $subscriptions);

        // Check that each subscription has an initial donation
        foreach ($subscriptions as $subscription) {
            $donations = $subscription->donations()->getAll();
            $this->assertGreaterThanOrEqual(1, count($donations));

            // Find the initial donation
            $initialDonation = null;
            foreach ($donations as $donation) {
                if ($donation->type->getValue() === 'SUBSCRIPTION') {
                    $initialDonation = $donation;
                    break;
                }
            }

            $this->assertNotNull($initialDonation);
            $this->assertEquals($subscription->id, $initialDonation->subscriptionId);
        }
    }

    /**
     * Test subscription frequency validation.
     *
     * @since 1.0.0
     */
    public function testSubscriptionFrequency()
    {
        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            2,
            'last_30_days',
            'test',
            'active',
            'month',
            3, // Every 3 months
            0
        );

        $this->assertEquals(2, $generated);

        $subscriptions = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->getAll();

        foreach ($subscriptions as $subscription) {
            $this->assertEquals(3, $subscription->frequency);
            $this->assertEquals('month', $subscription->period->getValue());
        }
    }

    /**
     * Test subscription installments.
     *
     * @since 1.0.0
     */
    public function testSubscriptionInstallments()
    {
        // Test with limited installments
        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            2,
            'last_30_days',
            'test',
            'active',
            'month',
            1,
            12 // 12 payments total
        );

        $this->assertEquals(2, $generated);

        $subscriptions = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->getAll();

        foreach ($subscriptions as $subscription) {
            $this->assertEquals(12, $subscription->installments);
            $this->assertFalse($subscription->isIndefinite());
        }

        // Test indefinite subscriptions (0 installments)
        $this->resetDatabase();
        $this->setUp();

        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            1,
            'last_30_days',
            'test',
            'active',
            'month',
            1,
            0 // Indefinite
        );

        $this->assertEquals(1, $generated);

        $subscription = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->get();

        $this->assertEquals(0, $subscription->installments);
        $this->assertTrue($subscription->isIndefinite());
    }

    /**
     * Test generating subscriptions with different statuses.
     *
     * @since 1.0.0
     */
    public function testGenerateSubscriptionsWithDifferentStatuses()
    {
        // Test specific statuses
        $statuses = ['pending', 'active', 'cancelled', 'expired'];

        foreach ($statuses as $status) {
            $this->resetDatabase();
            $this->setUp();

            $generated = $this->generator->generateSubscriptions(
                $this->testCampaign,
                1,
                'last_30_days',
                'test',
                $status,
                'month',
                1,
                0
            );

            $this->assertEquals(1, $generated);

            $subscription = Subscription::query()
                ->where('product_id', $this->testCampaign->defaultForm()->id)
                ->get();

            $this->assertEquals($status, $subscription->status->getValue());
        }
    }

    /**
     * Test that renewal dates are calculated correctly.
     *
     * @since 1.0.0
     */
    public function testRenewalDateCalculation()
    {
        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            1,
            'last_30_days',
            'test',
            'active',
            'month',
            1,
            0
        );

        $this->assertEquals(1, $generated);

        $subscription = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->get();

        // Renewal date should be after created date
        $this->assertGreaterThan($subscription->createdAt, $subscription->renewsAt);

        // For monthly subscription, renewal should be about 1 month later
        $interval = $subscription->createdAt->diff($subscription->renewsAt);
        $this->assertTrue($interval->m >= 1 || $interval->days >= 28);
    }

    /**
     * Test AJAX request handling with valid nonce.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestHandlingWithValidNonce()
    {
        $this->createAdminUser();

        $_POST = [
            'nonce' => wp_create_nonce('subscription_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'subscription_count' => 3,
            'date_range' => 'last_30_days',
            'subscription_mode' => 'test',
            'subscription_status' => 'active',
            'subscription_period' => 'month',
            'frequency' => 1,
            'installments' => 0,
            'start_date' => '',
            'end_date' => ''
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('Successfully generated 3 test subscriptions', $response['data']['message']);
    }

    /**
     * Test AJAX request handling with invalid nonce.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestHandlingWithInvalidNonce()
    {
        $this->createAdminUser();

        $_POST = [
            'nonce' => 'invalid_nonce',
            'campaign_id' => $this->testCampaign->id,
            'subscription_count' => 3,
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Security check failed.', $response['data']['message']);
    }

    /**
     * Test AJAX request handling without permissions.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestHandlingWithoutPermissions()
    {
        $this->createSubscriberUser();

        $_POST = [
            'nonce' => wp_create_nonce('subscription_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'subscription_count' => 3,
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('You do not have permission to perform this action.', $response['data']['message']);
    }

    /**
     * Test validation of subscription count limits.
     *
     * @since 1.0.0
     */
    public function testValidationOfSubscriptionCountLimits()
    {
        $this->createAdminUser();

        // Test count too high
        $_POST = [
            'nonce' => wp_create_nonce('subscription_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'subscription_count' => 1001, // Over limit
            'date_range' => 'last_30_days',
            'subscription_mode' => 'test',
            'subscription_status' => 'active',
            'subscription_period' => 'month',
            'frequency' => 1,
            'installments' => 0,
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Number of subscriptions must be between 1 and 1000.', $response['data']['message']);
    }

    /**
     * Test error handling for invalid campaign.
     *
     * @since 1.0.0
     */
    public function testErrorHandlingForInvalidCampaign()
    {
        $this->createAdminUser();

        $_POST = [
            'nonce' => wp_create_nonce('subscription_generator_nonce'),
            'campaign_id' => 99999, // Invalid campaign ID
            'subscription_count' => 5,
            'date_range' => 'last_30_days',
            'subscription_mode' => 'test',
            'subscription_status' => 'active',
            'subscription_period' => 'month',
            'frequency' => 1,
            'installments' => 0,
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid campaign selected.', $response['data']['message']);
    }

    /**
     * Test subscription renewal generation.
     *
     * @since 1.0.0
     */
    public function testSubscriptionRenewalGeneration()
    {
        $renewalsCount = 3;
        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            1,
            'last_30_days',
            'test',
            'active',
            'month',
            1,
            0,
            '',
            '',
            $renewalsCount
        );

        $this->assertEquals(1, $generated);

        // Check that the subscription was created
        $subscription = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->get();

        $this->assertNotNull($subscription);

        // Check that renewals were created (initial + renewals)
        $donations = $subscription->donations()->getAll();

        // Should have initial donation + renewal donations
        $this->assertCount($renewalsCount + 1, $donations);

        // Check that we have one initial donation and the rest are renewals
        $initialDonations = array_filter($donations, function($donation) {
            return $donation->type->getValue() === 'SUBSCRIPTION';
        });
        $renewalDonations = array_filter($donations, function($donation) {
            return $donation->type->getValue() === 'RENEWAL';
        });

        $this->assertCount(1, $initialDonations);
        $this->assertCount($renewalsCount, $renewalDonations);
    }

    /**
     * Test subscription generation without renewals.
     *
     * @since 1.0.0
     */
    public function testSubscriptionGenerationWithoutRenewals()
    {
        $generated = $this->generator->generateSubscriptions(
            $this->testCampaign,
            1,
            'last_30_days',
            'test',
            'active',
            'month',
            1,
            0,
            '',
            '',
            0 // No renewals
        );

        $this->assertEquals(1, $generated);

        $subscription = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->get();

        $this->assertNotNull($subscription);

        // Should only have the initial donation
        $donations = $subscription->donations()->getAll();

        $this->assertCount(1, $donations);
        $this->assertEquals('SUBSCRIPTION', $donations[0]->type->getValue());
    }

    /**
     * Test AJAX request handling with renewals.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestHandlingWithRenewals()
    {
        $this->createAdminUser();

        $_POST = [
            'nonce' => wp_create_nonce('subscription_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'subscription_count' => 1,
            'date_range' => 'last_30_days',
            'subscription_mode' => 'test',
            'subscription_status' => 'active',
            'subscription_period' => 'month',
            'frequency' => 1,
            'installments' => 0,
            'renewals_count' => 2,
            'start_date' => '',
            'end_date' => ''
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('Successfully generated 1 test subscriptions', $response['data']['message']);

        // Verify that renewals were created
        $subscription = Subscription::query()
            ->where('product_id', $this->testCampaign->defaultForm()->id)
            ->get();

        $donations = $subscription->donations()->getAll();

        // Should have initial + 2 renewals = 3 total donations
        $this->assertCount(3, $donations);
    }

    /**
     * Create an admin user and set as current user.
     */
    private function createAdminUser()
    {
        $user_id = wp_create_user('admin', 'password', 'admin@example.com');
        $user = new \WP_User($user_id);
        $user->set_role('administrator');
        wp_set_current_user($user_id);
    }

    /**
     * Create a subscriber user and set as current user.
     */
    private function createSubscriberUser()
    {
        $user_id = wp_create_user('subscriber', 'password', 'subscriber@example.com');
        $user = new \WP_User($user_id);
        $user->set_role('subscriber');
        wp_set_current_user($user_id);
    }

    /**
     * Reset the database for clean tests.
     */
    private function resetDatabase()
    {
        // Clean up any existing subscriptions and donations
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}give_subscriptions");
        $wpdb->query("DELETE FROM {$wpdb->prefix}posts WHERE post_type = 'give_payment'");
        $wpdb->query("DELETE FROM {$wpdb->prefix}give_donors");
    }
}
