<?php

namespace GiveDataGenerator\Tests\Unit\GiveDataGenerator;

use DateTime;
use Exception;
use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignGoalType;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Campaigns\ValueObjects\CampaignType;
use Give\Tests\TestCase;
use Give\Tests\TestTraits\RefreshDatabase;
use GiveDataGenerator\DataGenerator\CampaignGenerator;

class TestCampaignGenerator extends TestCase
{
    use RefreshDatabase;

    /**
     * @var CampaignGenerator
     */
    private $generator;

    /**
     * Set up test environment.
     *
     * @since 1.0.0
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->generator = new CampaignGenerator();
    }

    /**
     * Test generating campaigns with valid parameters.
     *
     * @since 1.0.0
     */
    public function testGenerateCampaignsWithValidParameters()
    {
        $campaignCount = 5;
        $generated = $this->generator->generateCampaigns(
            $campaignCount,
            'active',
            'amount',
            1000,
            10000,
            'blue_theme',
            true,
            true,
            '30_days',
            false,
            'Test Campaign'
        );

        $this->assertEquals($campaignCount, $generated);

        // Verify campaigns were created
        $campaigns = Campaign::query()->getAll();
        $this->assertCount($campaignCount, $campaigns);
    }

    /**
     * Test generating campaigns with different statuses.
     *
     * @since 1.0.0
     */
    public function testGenerateCampaignsWithDifferentStatuses()
    {
        $statuses = ['active', 'draft', 'inactive', 'pending', 'archived'];

        foreach ($statuses as $index => $status) {
            // Create campaign with unique title to avoid conflicts
            $generated = $this->generator->generateCampaigns(
                1,
                $status,
                'amount',
                1000,
                2000,
                'random',
                false,
                false,
                'ongoing',
                false,
                'Test Status ' . $index
            );

            $this->assertEquals(1, $generated);

            // Get all campaigns and find the one with our status
            $campaigns = Campaign::query()->getAll();
            $targetCampaign = null;

            foreach ($campaigns as $campaign) {
                if ($campaign->status->getValue() === $status) {
                    $targetCampaign = $campaign;
                    break;
                }
            }

            $this->assertNotNull($targetCampaign, "Campaign with status '{$status}' was not found");
            $this->assertEquals($status, $targetCampaign->status->getValue());
        }
    }

    /**
     * Test generating campaigns with different goal types.
     *
     * @since 1.0.0
     */
    public function testGenerateCampaignsWithDifferentGoalTypes()
    {
        $goalTypes = [
            'amount',
            'donations',
            'donors',
            'amountFromSubscriptions',
            'subscriptions',
            'donorsFromSubscriptions'
        ];

        foreach ($goalTypes as $index => $goalType) {
            // Create campaign with unique title
            $generated = $this->generator->generateCampaigns(
                1,
                'active',
                $goalType,
                10,
                100,
                'green_theme',
                false,
                false,
                'ongoing',
                false,
                'Test GoalType ' . $index
            );

            $this->assertEquals(1, $generated);

            // Get all campaigns and find the one with our goal type
            $campaigns = Campaign::query()->getAll();
            $targetCampaign = null;

            foreach ($campaigns as $campaign) {
                if ($campaign->goalType->getValue() === $goalType) {
                    $targetCampaign = $campaign;
                    break;
                }
            }

            $this->assertNotNull($targetCampaign, "Campaign with goal type '{$goalType}' was not found");
            $this->assertEquals($goalType, $targetCampaign->goalType->getValue());
        }
    }

    /**
     * Test generating campaigns with goal amounts within specified range.
     *
     * @since 1.0.0
     */
    public function testGenerateCampaignsWithGoalAmountsInRange()
    {
        $minGoal = 1000;
        $maxGoal = 5000;

        $generated = $this->generator->generateCampaigns(
            10,
            'active',
            'amount',
            $minGoal,
            $maxGoal,
            'random',
            false,
            false,
            'ongoing',
            false,
            'Test Range'
        );

        $this->assertEquals(10, $generated);

        $campaigns = Campaign::query()->getAll();

        foreach ($campaigns as $campaign) {
            $this->assertGreaterThanOrEqual($minGoal, $campaign->goal);
            $this->assertLessThanOrEqual($maxGoal, $campaign->goal);
        }
    }

    /**
     * Test generating campaigns with color schemes.
     *
     * @since 1.0.0
     */
    public function testGenerateCampaignsWithColorSchemes()
    {
        $colorSchemes = ['blue_theme', 'green_theme', 'red_theme', 'purple_theme', 'orange_theme'];

        foreach ($colorSchemes as $index => $scheme) {
            // Clear database for each test to avoid conflicts
            $this->refreshDatabase();

            $generated = $this->generator->generateCampaigns(
                1,
                'active',
                'amount',
                1000,
                2000,
                $scheme,
                false,
                false,
                'ongoing',
                false,
                'Test Color ' . $index
            );

            $this->assertEquals(1, $generated);

            $campaigns = Campaign::query()->getAll();
            $this->assertCount(1, $campaigns);

            $campaign = $campaigns[0];

            // Verify colors are valid hex codes
            $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $campaign->primaryColor);
            $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $campaign->secondaryColor);
        }
    }

    /**
     * Test generating campaigns with random colors.
     *
     * @since 1.0.0
     */
    public function testGenerateCampaignsWithRandomColors()
    {
        $generated = $this->generator->generateCampaigns(
            3,
            'active',
            'amount',
            1000,
            2000,
            'random',
            false,
            false,
            'ongoing',
            false,
            'Test Random Colors'
        );

        $this->assertEquals(3, $generated);

        $campaigns = Campaign::query()->getAll();

        foreach ($campaigns as $campaign) {
            // Verify colors are valid hex codes
            $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $campaign->primaryColor);
            $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $campaign->secondaryColor);
        }
    }

    /**
     * Test generating campaigns with descriptions.
     *
     * @since 1.0.0
     */
    public function testGenerateCampaignsWithDescriptions()
    {
        // Test with short description only
        $generated = $this->generator->generateCampaigns(
            1,
            'active',
            'amount',
            1000,
            2000,
            'blue_theme',
            true,
            false,
            'ongoing',
            false,
            'Test Short Desc'
        );

        $campaigns = Campaign::query()->getAll();
        $campaign = $campaigns[0];
        $this->assertNotEmpty($campaign->shortDescription);
        $this->assertEmpty($campaign->longDescription);

        // Clear and test with long description only
        $this->refreshDatabase();

        $generated = $this->generator->generateCampaigns(
            1,
            'active',
            'amount',
            1000,
            2000,
            'blue_theme',
            false,
            true,
            'ongoing',
            false,
            'Test Long Desc'
        );

        $campaigns = Campaign::query()->getAll();
        $campaign = $campaigns[0];
        $this->assertEmpty($campaign->shortDescription);
        $this->assertNotEmpty($campaign->longDescription);

        // Clear and test with both descriptions
        $this->refreshDatabase();

        $generated = $this->generator->generateCampaigns(
            1,
            'active',
            'amount',
            1000,
            2000,
            'blue_theme',
            true,
            true,
            'ongoing',
            false,
            'Test Both Desc'
        );

        $campaigns = Campaign::query()->getAll();
        $campaign = $campaigns[0];
        $this->assertNotEmpty($campaign->shortDescription);
        $this->assertNotEmpty($campaign->longDescription);
    }

    /**
     * Test generating campaigns with different durations.
     *
     * @since 1.0.0
     */
    public function testGenerateCampaignsWithDifferentDurations()
    {
        $durations = ['ongoing', '30_days', '60_days', '90_days', '6_months', '1_year'];

        foreach ($durations as $index => $duration) {
            // Clear database for each test
            $this->refreshDatabase();

            $generated = $this->generator->generateCampaigns(
                1,
                'active',
                'amount',
                1000,
                2000,
                'blue_theme',
                false,
                false,
                $duration,
                false,
                'Test Duration ' . $index
            );

            $this->assertEquals(1, $generated);

            $campaigns = Campaign::query()->getAll();
            $this->assertCount(1, $campaigns);

            $campaign = $campaigns[0];

            if ($duration === 'ongoing') {
                $this->assertNull($campaign->endDate);
            } else {
                $this->assertNotNull($campaign->endDate);
                $this->assertGreaterThan($campaign->startDate, $campaign->endDate);
            }
        }
    }

    /**
     * Test generating campaigns with title prefix.
     *
     * @since 1.0.0
     */
    public function testGenerateCampaignsWithTitlePrefix()
    {
        $prefix = 'Test Prefix';

        $generated = $this->generator->generateCampaigns(
            3,
            'active',
            'amount',
            1000,
            2000,
            'blue_theme',
            false,
            false,
            'ongoing',
            false,
            $prefix
        );

        $this->assertEquals(3, $generated);

        $campaigns = Campaign::query()->getAll();

        foreach ($campaigns as $campaign) {
            $this->assertStringStartsWith($prefix . ' - ', $campaign->title);
        }
    }

    /**
     * Test generating campaigns without title prefix.
     *
     * @since 1.0.0
     */
    public function testGenerateCampaignsWithoutTitlePrefix()
    {
        $generated = $this->generator->generateCampaigns(
            2,
            'active',
            'amount',
            1000,
            2000,
            'blue_theme',
            false,
            false,
            'ongoing',
            false,
            ''
        );

        $this->assertEquals(2, $generated);

        $campaigns = Campaign::query()->getAll();

        foreach ($campaigns as $campaign) {
            $this->assertNotEmpty($campaign->title);
            $this->assertStringNotContainsString(' - ', $campaign->title);
        }
    }

    /**
     * Test that campaigns have proper core type.
     *
     * @since 1.0.0
     */
    public function testCampaignsHaveProperCoreType()
    {
        $generated = $this->generator->generateCampaigns(
            3,
            'active',
            'amount',
            1000,
            2000,
            'blue_theme',
            false,
            false,
            'ongoing',
            false,
            'Test Core Type'
        );

        $campaigns = Campaign::query()->getAll();

        foreach ($campaigns as $campaign) {
            $this->assertEquals('core', $campaign->type->getValue());
        }
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
            'nonce' => wp_create_nonce('campaign_generator_nonce'),
            'campaign_count' => 3,
            'campaign_status' => 'active',
            'goal_type' => 'amount',
            'goal_amount_min' => 1000,
            'goal_amount_max' => 5000,
            'color_scheme' => 'blue_theme',
            'include_short_desc' => '1',
            'include_long_desc' => '1',
            'campaign_duration' => '30_days',
            'create_forms' => '',
            'campaign_title_prefix' => 'Test Campaign Ajax'
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('Successfully generated 3 test campaigns', $response['data']['message']);
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
            'campaign_count' => 3,
            'campaign_status' => 'active',
            'goal_type' => 'amount',
            'goal_amount_min' => 1000,
            'goal_amount_max' => 5000,
            'color_scheme' => 'blue_theme',
            'campaign_duration' => 'ongoing',
            'campaign_title_prefix' => 'Test Invalid Nonce'
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Security check failed.', $response['data']['message']);
    }

    /**
     * Test AJAX request handling without proper permissions.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestHandlingWithoutPermissions()
    {
        $this->createSubscriberUser();

        $_POST = [
            'nonce' => wp_create_nonce('campaign_generator_nonce'),
            'campaign_count' => 3,
            'campaign_status' => 'active',
            'goal_type' => 'amount',
            'goal_amount_min' => 1000,
            'goal_amount_max' => 5000,
            'color_scheme' => 'blue_theme',
            'campaign_duration' => 'ongoing',
            'campaign_title_prefix' => 'Test No Permission'
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('You do not have permission to perform this action.', $response['data']['message']);
    }

    /**
     * Test validation of campaign count limits.
     *
     * @since 1.0.0
     */
    public function testValidationOfCampaignCountLimits()
    {
        $this->createAdminUser();

        // Test count too low
        $_POST = [
            'nonce' => wp_create_nonce('campaign_generator_nonce'),
            'campaign_count' => 0,
            'campaign_status' => 'active',
            'goal_type' => 'amount',
            'goal_amount_min' => 1000,
            'goal_amount_max' => 5000,
            'color_scheme' => 'blue_theme',
            'campaign_duration' => 'ongoing',
            'campaign_title_prefix' => 'Test Count Low'
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Number of campaigns must be between 1 and 50.', $response['data']['message']);

        // Test count too high
        $_POST['campaign_count'] = 51;
        $_POST['campaign_title_prefix'] = 'Test Count High';

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Number of campaigns must be between 1 and 50.', $response['data']['message']);
    }

    /**
     * Test validation of goal amount range.
     *
     * @since 1.0.0
     */
    public function testValidationOfGoalAmountRange()
    {
        $this->createAdminUser();

        $_POST = [
            'nonce' => wp_create_nonce('campaign_generator_nonce'),
            'campaign_count' => 3,
            'campaign_status' => 'active',
            'goal_type' => 'amount',
            'goal_amount_min' => 5000,
            'goal_amount_max' => 1000, // max less than min
            'color_scheme' => 'blue_theme',
            'campaign_duration' => 'ongoing',
            'campaign_title_prefix' => 'Test Invalid Range'
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Minimum goal amount must be less than maximum goal amount.', $response['data']['message']);
    }

    /**
     * Test error handling during campaign generation.
     *
     * @since 1.0.0
     */
    public function testErrorHandlingDuringGeneration()
    {
        // Test with an invalid goal type to trigger an exception
        $generated = 0;

        try {
            $generated = $this->generator->generateCampaigns(
                1,
                'active',
                'invalid_goal_type',
                1000,
                2000,
                'blue_theme',
                false,
                false,
                'ongoing',
                false,
                'Test Error Handling'
            );
        } catch (Exception $e) {
            $this->assertStringContainsString('No campaigns were generated', $e->getMessage());
        }

        $this->assertEquals(0, $generated);
    }

    /**
     * Create an admin user for testing.
     *
     * @since 1.0.0
     */
    private function createAdminUser()
    {
        $userId = wp_insert_user([
            'user_login' => 'test_admin_' . time() . '_' . rand(1000, 9999),
            'user_email' => 'admin_' . time() . '@test.com',
            'user_pass' => 'password',
            'role' => 'administrator'
        ]);

        wp_set_current_user($userId);
    }

    /**
     * Create a subscriber user for testing.
     *
     * @since 1.0.0
     */
    private function createSubscriberUser()
    {
        $userId = wp_insert_user([
            'user_login' => 'test_subscriber_' . time() . '_' . rand(1000, 9999),
            'user_email' => 'subscriber_' . time() . '@test.com',
            'user_pass' => 'password',
            'role' => 'subscriber'
        ]);

        wp_set_current_user($userId);
    }
}
