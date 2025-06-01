<?php

namespace GiveDataGenerator\Tests\Unit\GiveDataGenerator;

use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignGoalType;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Campaigns\ValueObjects\CampaignType;
use Give\Donations\Models\Donation;
use Give\Donors\Models\Donor;
use Give\Tests\TestCase;
use Give\Tests\TestTraits\RefreshDatabase;
use GiveDataGenerator\DataGenerator\AdminSettings;
use GiveDataGenerator\DataGenerator\DonationGenerator;
use GiveDataGenerator\DataGenerator\ServiceProvider;

class TestIntegration extends TestCase
{
    use RefreshDatabase;

    /**
     * @var ServiceProvider
     */
    private $serviceProvider;

    /**
     * @var AdminSettings
     */
    private $adminSettings;

    /**
     * @var DonationGenerator
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

        $this->serviceProvider = new ServiceProvider();
        $this->adminSettings = new AdminSettings();
        $this->generator = new DonationGenerator();

        // Create a test campaign
        $this->testCampaign = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Integration Test Campaign',
            'shortDescription' => 'Test campaign description',
            'longDescription' => 'Test campaign long description',
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
     * Test complete workflow from service provider to donation creation.
     *
     * @since 1.0.0
     */
    public function testCompleteWorkflowFromServiceProviderToDonationCreation()
    {
        // Boot service provider
        $this->serviceProvider->boot();

        // Verify hooks are registered
        $this->assertTrue(has_action('admin_menu'));
        $this->assertTrue(has_action('wp_ajax_generate_test_donations'));

        // Generate donations using the full workflow
        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            10,
            'last_30_days',
            'test'
        );

        $this->assertEquals(10, $generated);

        // Verify donations were created
        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        $this->assertCount(10, $donations);

        // Verify donors were created
        foreach ($donations as $donation) {
            $donor = Donor::find($donation->donorId);
            $this->assertNotNull($donor);
            $this->assertEquals($donation->email, $donor->email);
        }
    }

    /**
     * Test end-to-end AJAX workflow.
     *
     * @since 1.0.0
     */
    public function testEndToEndAjaxWorkflow()
    {
        // Set up admin user
        $user = $this->factory()->user->create_and_get([
            'role' => 'administrator'
        ]);
        wp_set_current_user($user->ID);

        // Boot service provider
        $this->serviceProvider->boot();

        // Simulate AJAX request
        $_POST = [
            'nonce' => wp_create_nonce('test_donation_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'donation_count' => 5,
            'date_range' => 'last_90_days',
            'donation_mode' => 'test',
            'start_date' => '',
            'end_date' => ''
        ];

        // Capture AJAX response
        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        // Verify AJAX response
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('Successfully generated 5 test donations', $response['data']['message']);

        // Verify donations in database
        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        $this->assertCount(5, $donations);

        // Verify all donations are in test mode
        foreach ($donations as $donation) {
            $this->assertEquals('test', $donation->mode->getValue());
            $this->assertEquals('COMPLETE', $donation->status->getValue());
        }
    }

    /**
     * Test multiple campaigns with different settings.
     *
     * @since 1.0.0
     */
    public function testMultipleCampaignsWithDifferentSettings()
    {
        // Create additional campaigns
        $campaign2 = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Second Campaign',
            'shortDescription' => 'Test campaign description',
            'longDescription' => 'Test campaign long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 300000,
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);

        $campaign3 = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Third Campaign',
            'shortDescription' => 'Test campaign description',
            'longDescription' => 'Test campaign long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 700000,
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);

        // Generate donations for first campaign (test mode)
        $generated1 = $this->generator->generateDonations(
            $this->testCampaign,
            3,
            'last_30_days',
            'test'
        );

        // Generate donations for second campaign (live mode)
        $generated2 = $this->generator->generateDonations(
            $campaign2,
            5,
            'last_90_days',
            'live'
        );

        // Generate donations for third campaign (custom date range)
        $generated3 = $this->generator->generateDonations(
            $campaign3,
            2,
            'custom',
            'test',
            '2024-01-01',
            '2024-01-31'
        );

        // Verify generation counts
        $this->assertEquals(3, $generated1);
        $this->assertEquals(5, $generated2);
        $this->assertEquals(2, $generated3);

        // Verify campaign-specific donations
        $donations1 = Donation::query()->where('campaignId', $this->testCampaign->id)->getAll();
        $donations2 = Donation::query()->where('campaignId', $campaign2->id)->getAll();
        $donations3 = Donation::query()->where('campaignId', $campaign3->id)->getAll();

        $this->assertCount(3, $donations1);
        $this->assertCount(5, $donations2);
        $this->assertCount(2, $donations3);

        // Verify donation modes
        foreach ($donations1 as $donation) {
            $this->assertEquals('test', $donation->mode->getValue());
        }

        foreach ($donations2 as $donation) {
            $this->assertEquals('live', $donation->mode->getValue());
        }

        foreach ($donations3 as $donation) {
            $this->assertEquals('test', $donation->mode->getValue());
        }
    }

    /**
     * Test donor reuse across multiple donations.
     *
     * @since 1.0.0
     */
    public function testDonorReuseAcrossMultipleDonations()
    {
        // Generate a larger number of donations to increase chance of email collision
        $this->generator->generateDonations(
            $this->testCampaign,
            50,
            'last_30_days',
            'test'
        );

        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        $this->assertCount(50, $donations);

        // Count unique donors
        $uniqueDonorIds = array_unique(array_map(function($donation) {
            return $donation->donorId;
        }, $donations));

        // Should have fewer unique donors than donations (some email reuse)
        $this->assertLessThan(50, count($uniqueDonorIds));

        // Verify donor data consistency
        foreach ($donations as $donation) {
            $donor = Donor::find($donation->donorId);
            $this->assertEquals($donation->email, $donor->email);
            $this->assertEquals($donation->firstName, $donor->firstName);
            $this->assertEquals($donation->lastName, $donor->lastName);
        }
    }

    /**
     * Test admin interface integration with campaigns.
     *
     * @since 1.0.0
     */
    public function testAdminInterfaceIntegrationWithCampaigns()
    {
        // Create multiple campaigns
        $campaign2 = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Another Campaign',
            'shortDescription' => 'Test campaign description',
            'longDescription' => 'Test campaign long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 200000,
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);

        // Render admin page
        ob_start();
        $this->adminSettings->renderAdminPage();
        $output = ob_get_clean();

        // Check that campaigns appear in the dropdown
        $this->assertStringContainsString('Integration Test Campaign', $output);
        $this->assertStringContainsString('Another Campaign', $output);
        $this->assertStringContainsString('value="' . $this->testCampaign->id . '"', $output);
        $this->assertStringContainsString('value="' . $campaign2->id . '"', $output);
    }

    /**
     * Test error handling in complete workflow.
     *
     * @since 1.0.0
     */
    public function testErrorHandlingInCompleteWorkflow()
    {
        $user = $this->factory()->user->create_and_get([
            'role' => 'administrator'
        ]);
        wp_set_current_user($user->ID);

        // Test with missing campaign
        $_POST = [
            'nonce' => wp_create_nonce('test_donation_generator_nonce'),
            'campaign_id' => 99999, // Non-existent
            'donation_count' => 3,
            'date_range' => 'last_30_days',
            'donation_mode' => 'test',
            'start_date' => '',
            'end_date' => ''
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid campaign selected.', $response['data']['message']);

        // Test with invalid donation count
        $_POST['campaign_id'] = $this->testCampaign->id;
        $_POST['donation_count'] = 2000; // Over limit

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Number of donations must be between 1 and 1000.', $response['data']['message']);
    }

    /**
     * Test performance with larger datasets.
     *
     * @since 1.0.0
     */
    public function testPerformanceWithLargerDatasets()
    {
        $startTime = microtime(true);

        // Generate 100 donations
        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            100,
            'last_year',
            'test'
        );

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete in reasonable time (less than 30 seconds)
        $this->assertLessThan(30, $executionTime);
        $this->assertEquals(100, $generated);

        // Verify all donations were created
        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        $this->assertCount(100, $donations);
    }

    /**
     * Test data validation across all components.
     *
     * @since 1.0.0
     */
    public function testDataValidationAcrossAllComponents()
    {
        $this->generator->generateDonations(
            $this->testCampaign,
            20,
            'last_90_days',
            'live'
        );

        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        foreach ($donations as $donation) {
            // Validate required fields
            $this->assertNotEmpty($donation->firstName);
            $this->assertNotEmpty($donation->lastName);
            $this->assertNotEmpty($donation->email);
            $this->assertStringContainsString('@', $donation->email);

            // Validate amounts
            $this->assertGreaterThan(0, $donation->amount->getMinorAmount());
            $this->assertGreaterThanOrEqual(0, $donation->feeAmountRecovered->getMinorAmount());

            // Validate status and mode
            $this->assertEquals('COMPLETE', $donation->status->getValue());
            $this->assertEquals('live', $donation->mode->getValue());

            // Validate campaign association
            $this->assertEquals($this->testCampaign->id, $donation->campaignId);

            // Validate donor consistency
            $donor = Donor::find($donation->donorId);
            $this->assertNotNull($donor);
            $this->assertEquals($donation->email, $donor->email);

            // Validate transaction data
            $this->assertNotEmpty($donation->gatewayTransactionId);
            $this->assertNotEmpty($donation->purchaseKey);
        }
    }
}
