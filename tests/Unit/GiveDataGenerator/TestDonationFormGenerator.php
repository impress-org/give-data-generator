<?php

namespace GiveDataGenerator\Tests\Unit\GiveDataGenerator;

use Exception;
use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignGoalType;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Campaigns\ValueObjects\CampaignType;
use Give\Tests\TestCase;
use Give\Tests\TestTraits\RefreshDatabase;
use GiveDataGenerator\DataGenerator\DonationFormGenerator;

class TestDonationFormGenerator extends TestCase
{
    use RefreshDatabase;

    /**
     * @var DonationFormGenerator
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

        $this->generator = new DonationFormGenerator();

        // Create a test campaign
        $this->testCampaign = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Test Campaign for Forms',
            'shortDescription' => 'Test campaign for form generation',
            'longDescription' => 'Test campaign long description for form generation',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 500000, // $5000
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);
    }

    /**
     * Test generating donation forms with valid parameters.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationFormsWithValidParameters()
    {
        $formCount = 3;
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            $formCount,
            'published',
            false,
            'amount',
            500,
            5000,
            false,
            'Test Form',
            true
        );

        $this->assertEquals($formCount, $generated);
    }

    /**
     * Test generating forms with different statuses.
     *
     * @since 1.0.0
     */
    public function testGenerateFormsWithDifferentStatuses()
    {
        // Test published status
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            1,
            'published',
            false,
            'amount',
            500,
            5000,
            false,
            '',
            false
        );
        $this->assertEquals(1, $generated);

        // Test draft status
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            1,
            'draft',
            false,
            'amount',
            500,
            5000,
            false,
            '',
            false
        );
        $this->assertEquals(1, $generated);

        // Test private status
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            1,
            'private',
            false,
            'amount',
            500,
            5000,
            false,
            '',
            false
        );
        $this->assertEquals(1, $generated);
    }

    /**
     * Test generating forms with goals enabled and different goal types.
     *
     * @since 1.0.0
     */
    public function testGenerateFormsWithDifferentGoalTypes()
    {
        // Test campaign goal type
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            1,
            'published',
            true,
            'campaign',
            500,
            5000,
            false,
            '',
            true
        );
        $this->assertEquals(1, $generated);

        // Test custom amount goal
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            1,
            'published',
            true,
            'amount',
            100000,
            500000,
            false,
            '',
            false
        );
        $this->assertEquals(1, $generated);

        // Test donors goal type
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            1,
            'published',
            true,
            'donors',
            10,
            100,
            false,
            '',
            false
        );
        $this->assertEquals(1, $generated);

        // Test donations goal type
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            1,
            'published',
            true,
            'donations',
            5,
            50,
            false,
            '',
            false
        );
        $this->assertEquals(1, $generated);
    }

    /**
     * Test generating forms with goals disabled.
     *
     * @since 1.0.0
     */
    public function testGenerateFormsWithGoalsDisabled()
    {
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            1,
            'published',
            false,
            'amount',
            500,
            5000,
            false,
            '',
            false
        );

        $this->assertEquals(1, $generated);
    }

    /**
     * Test generating forms with campaign color inheritance.
     *
     * @since 1.0.0
     */
    public function testGenerateFormsWithCampaignColorInheritance()
    {
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            1,
            'published',
            false,
            'amount',
            500,
            5000,
            false,
            '',
            true
        );

        $this->assertEquals(1, $generated);
    }

    /**
     * Test generating forms with custom title prefix.
     *
     * @since 1.0.0
     */
    public function testGenerateFormsWithCustomTitlePrefix()
    {
        $titlePrefix = 'Custom Prefix';

        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            1,
            'published',
            false,
            'amount',
            500,
            5000,
            false,
            $titlePrefix,
            false
        );

        $this->assertEquals(1, $generated);
    }

    /**
     * Test generating forms with random designs enabled.
     *
     * @since 1.0.0
     */
    public function testGenerateFormsWithRandomDesigns()
    {
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            5,
            'published',
            false,
            'amount',
            500,
            5000,
            true,
            '',
            false
        );

        $this->assertEquals(5, $generated);
    }

    /**
     * Test AJAX request handling with valid nonce and permissions.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestHandlingWithValidNonce()
    {
        wp_set_current_user($this->createAdminUser());

        $_POST = [
            'nonce' => wp_create_nonce('donation_form_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'form_count' => 2,
            'form_status' => 'published',
            'enable_goals' => '',
            'goal_type' => 'amount',
            'goal_amount_min' => 500,
            'goal_amount_max' => 5000,
            'random_designs' => '1',
            'form_title_prefix' => 'Test',
            'inherit_campaign_colors' => '1'
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals(2, $response['data']['generated']);
    }

    /**
     * Test AJAX request handling with invalid nonce.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestHandlingWithInvalidNonce()
    {
        wp_set_current_user($this->createAdminUser());

        $_POST = [
            'nonce' => 'invalid_nonce',
            'campaign_id' => $this->testCampaign->id,
            'form_count' => 1,
            'form_status' => 'published'
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
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
        wp_set_current_user($this->createSubscriberUser());

        $_POST = [
            'nonce' => wp_create_nonce('donation_form_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'form_count' => 1,
            'form_status' => 'published'
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('You do not have permission to perform this action.', $response['data']['message']);
    }

    /**
     * Test validation of form count limits.
     *
     * @since 1.0.0
     */
    public function testValidationOfFormCountLimits()
    {
        wp_set_current_user($this->createAdminUser());

        // Test with form count too high
        $_POST = [
            'nonce' => wp_create_nonce('donation_form_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'form_count' => 25, // Over the limit
            'form_status' => 'published'
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Number of forms must be between 1 and 20.', $response['data']['message']);

        // Test with form count too low
        $_POST['form_count'] = 0;

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Number of forms must be between 1 and 20.', $response['data']['message']);
    }

    /**
     * Test error handling for invalid campaign.
     *
     * @since 1.0.0
     */
    public function testErrorHandlingForInvalidCampaign()
    {
        wp_set_current_user($this->createAdminUser());

        $_POST = [
            'nonce' => wp_create_nonce('donation_form_generator_nonce'),
            'campaign_id' => 99999, // Non-existent campaign
            'form_count' => 1,
            'form_status' => 'published'
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Selected campaign not found.', $response['data']['message']);
    }

    /**
     * Test validation of goal amount ranges.
     *
     * @since 1.0.0
     */
    public function testValidationOfGoalAmountRanges()
    {
        wp_set_current_user($this->createAdminUser());

        $_POST = [
            'nonce' => wp_create_nonce('donation_form_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'form_count' => 1,
            'form_status' => 'published',
            'enable_goals' => '1',
            'goal_type' => 'amount',
            'goal_amount_min' => 5000, // Higher than max
            'goal_amount_max' => 1000
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Minimum goal amount must be less than maximum goal amount.', $response['data']['message']);
    }

    /**
     * Test missing campaign validation.
     *
     * @since 1.0.0
     */
    public function testMissingCampaignValidation()
    {
        wp_set_current_user($this->createAdminUser());

        $_POST = [
            'nonce' => wp_create_nonce('donation_form_generator_nonce'),
            'campaign_id' => '', // Missing campaign
            'form_count' => 1,
            'form_status' => 'published'
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Please select a campaign.', $response['data']['message']);
    }

    /**
     * Test error handling during form generation with extreme values.
     *
     * @since 1.0.0
     */
    public function testErrorHandlingDuringFormGeneration()
    {
        // Test with edge case values
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            2,
            'published',
            true,
            'amount',
            1,
            PHP_INT_MAX,
            false,
            str_repeat('Very Long Title Prefix ', 50), // Very long prefix
            false
        );

        // Should still generate forms even with edge case values
        $this->assertGreaterThanOrEqual(0, $generated);
        $this->assertLessThanOrEqual(2, $generated);
    }

    /**
     * Test generating multiple forms with different configurations.
     *
     * @since 1.0.0
     */
    public function testGenerateMultipleFormsWithDifferentConfigurations()
    {
        // Generate forms with different parameters to test variety
        $configurations = [
            ['published', true, 'campaign', true],
            ['draft', false, 'amount', false],
            ['private', true, 'donors', true],
            ['published', true, 'donations', false],
        ];

        foreach ($configurations as $index => $config) {
            [$status, $enableGoals, $goalType, $randomDesigns] = $config;

            $generated = $this->generator->generateDonationForms(
                $this->testCampaign,
                1,
                $status,
                $enableGoals,
                $goalType,
                100,
                1000,
                $randomDesigns,
                "Config {$index}",
                true
            );

            $this->assertEquals(1, $generated, "Failed to generate form with configuration {$index}");
        }
    }

    /**
     * Test performance with multiple form generation.
     *
     * @since 1.0.0
     */
    public function testPerformanceWithMultipleFormGeneration()
    {
        $startTime = microtime(true);

        // Generate maximum allowed forms
        $generated = $this->generator->generateDonationForms(
            $this->testCampaign,
            20, // Maximum allowed
            'published',
            true,
            'amount',
            1000,
            10000,
            true,
            'Performance Test',
            true
        );

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->assertEquals(20, $generated);
        $this->assertLessThan(30, $duration, 'Form generation took too long (over 30 seconds)');
    }

    /**
     * Create an admin user for testing.
     *
     * @since 1.0.0
     * @return int User ID
     */
    private function createAdminUser()
    {
        return wp_insert_user([
            'user_login' => 'testadmin',
            'user_pass' => 'password',
            'user_email' => 'admin@test.com',
            'role' => 'administrator'
        ]);
    }

    /**
     * Create a subscriber user for testing.
     *
     * @since 1.0.0
     * @return int User ID
     */
    private function createSubscriberUser()
    {
        return wp_insert_user([
            'user_login' => 'testsubscriber',
            'user_pass' => 'password',
            'user_email' => 'subscriber@test.com',
            'role' => 'subscriber'
        ]);
    }
}
