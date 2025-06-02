<?php

namespace GiveDataGenerator\Tests\Unit\GiveDataGenerator;

use DateTime;
use Exception;
use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignGoalType;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Campaigns\ValueObjects\CampaignType;
use Give\Donations\Models\Donation;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Donors\Models\Donor;
use Give\Tests\TestCase;
use Give\Tests\TestTraits\RefreshDatabase;
use GiveDataGenerator\DataGenerator\DonationGenerator;

class TestDonationGenerator extends TestCase
{
    use RefreshDatabase;

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

        $this->generator = new DonationGenerator();

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
     * Test generating donations with valid parameters.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsWithValidParameters()
    {
        $donationCount = 5;
        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            $donationCount,
            'last_30_days',
            'test',
            'complete',
            'create_new',
            0
        );

        $this->assertEquals($donationCount, $generated);

        // Verify donations were created
        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        $this->assertCount($donationCount, $donations);
    }

    /**
     * Test generating donations with test mode.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsInTestMode()
    {
        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            3,
            'last_30_days',
            'test',
            'complete',
            'create_new',
            0
        );

        $this->assertEquals(3, $generated);

        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        foreach ($donations as $donation) {
            $this->assertEquals('test', $donation->mode->getValue());
            $this->assertEquals('COMPLETE', $donation->status->getValue());
        }
    }

    /**
     * Test generating donations with live mode.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsInLiveMode()
    {
        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            2,
            'last_30_days',
            'live',
            'complete',
            'create_new',
            0
        );

        $this->assertEquals(2, $generated);

        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        foreach ($donations as $donation) {
            $this->assertEquals('live', $donation->mode->getValue());
        }
    }

    /**
     * Test generating donations with custom date range.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsWithCustomDateRange()
    {
        $startDate = '2024-01-01';
        $endDate = '2024-01-31';

        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            3,
            'custom',
            'test',
            'complete',
            'create_new',
            0,
            $startDate,
            $endDate
        );

        $this->assertEquals(3, $generated);

        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        foreach ($donations as $donation) {
            $createdAt = new DateTime($donation->createdAt);
            $this->assertGreaterThanOrEqual(new DateTime($startDate), $createdAt);
            $this->assertLessThanOrEqual(new DateTime($endDate), $createdAt);
        }
    }

    /**
     * Test generating donations with last 90 days range.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsWithLast90Days()
    {
        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            2,
            'last_90_days',
            'test',
            'complete',
            'create_new',
            0
        );

        $this->assertEquals(2, $generated);

        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        $ninetyDaysAgo = (new DateTime())->modify('-90 days');

        foreach ($donations as $donation) {
            $createdAt = new DateTime($donation->createdAt);
            $this->assertGreaterThanOrEqual($ninetyDaysAgo, $createdAt);
        }
    }

    /**
     * Test that donations have realistic amounts.
     *
     * @since 1.0.0
     */
    public function testDonationsHaveRealisticAmounts()
    {
        $this->generator->generateDonations(
            $this->testCampaign,
            10,
            'last_30_days',
            'test',
            'complete',
            'create_new',
            0
        );

        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        foreach ($donations as $donation) {
            $amount = $donation->amount->getMinorAmount() / 100; // Convert cents to dollars
            $this->assertGreaterThanOrEqual(5, $amount);
            $this->assertLessThanOrEqual(500, $amount);
        }
    }

    /**
     * Test that donors are created with proper data.
     *
     * @since 1.0.0
     */
    public function testDonorsAreCreatedWithProperData()
    {
        $this->generator->generateDonations(
            $this->testCampaign,
            5,
            'last_30_days',
            'test',
            'complete',
            'create_new',
            0
        );

        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        foreach ($donations as $donation) {
            $this->assertNotEmpty($donation->firstName);
            $this->assertNotEmpty($donation->lastName);
            $this->assertNotEmpty($donation->email);
            $this->assertStringContainsString('@', $donation->email);

            // Verify donor exists
            $donor = Donor::find($donation->donorId);
            $this->assertNotNull($donor);
            $this->assertEquals($donation->firstName, $donor->firstName);
            $this->assertEquals($donation->lastName, $donor->lastName);
            $this->assertEquals($donation->email, $donor->email);
        }
    }

    /**
     * Test that some donations have fee amounts.
     *
     * @since 1.0.0
     */
    public function testSomeDonationsHaveFeeAmounts()
    {
        $this->generator->generateDonations(
            $this->testCampaign,
            20, // Generate more to ensure we get some with fees
            'last_30_days',
            'test',
            'complete',
            'create_new',
            0
        );

        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        $donationsWithFees = array_filter($donations, function($donation) {
            return $donation->feeAmountRecovered->getMinorAmount() > 0;
        });

        // Should have some donations with fees (not all, due to 70% no-fee chance)
        $this->assertGreaterThan(0, count($donationsWithFees));

        // Fee amounts should be reasonable (not more than 10% of donation)
        foreach ($donationsWithFees as $donation) {
            $feeAmount = $donation->feeAmountRecovered->getMinorAmount();
            $donationAmount = $donation->amount->getMinorAmount();
            $maxFee = $donationAmount * 0.1; // 10% max

            $this->assertLessThanOrEqual($maxFee, $feeAmount);
        }
    }

    /**
     * Test AJAX request handling with valid nonce.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestHandlingWithValidNonce()
    {
        // Set up user with proper capabilities
        $user = $this->createAdminUser();
        wp_set_current_user($user->ID);

        $_POST = [
            'nonce' => wp_create_nonce('test_donation_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'donation_count' => 3,
            'date_range' => 'last_30_days',
            'donation_mode' => 'test',
            'donation_status' => 'complete',
            'start_date' => '',
            'end_date' => ''
        ];

        // Capture the output
        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertStringContainsString('Successfully generated 3 test donations', $response['data']['message']);
    }

    /**
     * Test AJAX request handling with invalid nonce.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestHandlingWithInvalidNonce()
    {
        $_POST = [
            'nonce' => 'invalid_nonce',
            'campaign_id' => $this->testCampaign->id,
            'donation_count' => 3,
            'date_range' => 'last_30_days',
            'donation_mode' => 'test',
            'donation_status' => 'complete',
            'start_date' => '',
            'end_date' => ''
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
        // Set up user without proper capabilities
        $user = $this->createSubscriberUser();
        wp_set_current_user($user->ID);

        $_POST = [
            'nonce' => wp_create_nonce('test_donation_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'donation_count' => 3,
            'date_range' => 'last_30_days',
            'donation_mode' => 'test',
            'donation_status' => 'complete',
            'start_date' => '',
            'end_date' => ''
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('You do not have permission to perform this action.', $response['data']['message']);
    }

    /**
     * Test error handling for invalid campaign.
     *
     * @since 1.0.0
     */
    public function testErrorHandlingForInvalidCampaign()
    {
        $user = $this->createAdminUser();
        wp_set_current_user($user->ID);

        $_POST = [
            'nonce' => wp_create_nonce('test_donation_generator_nonce'),
            'campaign_id' => 99999, // Non-existent campaign
            'donation_count' => 3,
            'date_range' => 'last_30_days',
            'donation_mode' => 'test',
            'donation_status' => 'complete',
            'start_date' => '',
            'end_date' => ''
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid campaign selected.', $response['data']['message']);
    }

    /**
     * Test validation of donation count limits.
     *
     * @since 1.0.0
     */
    public function testValidationOfDonationCountLimits()
    {
        $user = $this->createAdminUser();
        wp_set_current_user($user->ID);

        // Test with count over limit
        $_POST = [
            'nonce' => wp_create_nonce('test_donation_generator_nonce'),
            'campaign_id' => $this->testCampaign->id,
            'donation_count' => 1500, // Over 1000 limit
            'date_range' => 'last_30_days',
            'donation_mode' => 'test',
            'donation_status' => 'complete',
            'start_date' => '',
            'end_date' => ''
        ];

        ob_start();
        $this->generator->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Number of donations must be between 1 and 1000.', $response['data']['message']);
    }

    /**
     * Test generation with different donation statuses.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsWithDifferentStatuses()
    {
        // Test that the method accepts the donation status parameter without errors
        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            3,
            'last_30_days',
            'test',
            'pending',
            'create_new',
            0
        );

        $this->assertEquals(3, $generated);

        // Test with refunded status
        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            2,
            'last_30_days',
            'test',
            'refunded',
            'create_new',
            0
        );

        $this->assertEquals(2, $generated);

        // Test with random status
        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            1,
            'last_30_days',
            'test',
            'random',
            'create_new',
            0
        );

        $this->assertEquals(1, $generated);

        // Verify donations were created (basic count check)
        $allDonations = Donation::query()->getAll();
        $this->assertGreaterThanOrEqual(6, count($allDonations));
    }

    /**
     * Test creating donations with new donors only.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsWithCreateNewDonors()
    {
        // Create some existing donors first
        $existingDonor = Donor::create([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'name' => 'John Doe',
            'email' => 'existing@test.com',
        ]);

        $initialDonorCount = Donor::query()->count();

        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            3,
            'last_30_days',
            'test',
            'complete',
            'create_new',
            0
        );

        $this->assertEquals(3, $generated);

        // Should have created 3 new donors
        $finalDonorCount = Donor::query()->count();
        $this->assertEquals($initialDonorCount + 3, $finalDonorCount);

        // Verify that none of the donations used the existing donor
        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        foreach ($donations as $donation) {
            $this->assertNotEquals($existingDonor->id, $donation->donorId);
        }
    }

    /**
     * Test creating donations with existing donors only.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsWithUseExistingDonors()
    {
        // Create some existing donors first
        $existingDonors = [];
        for ($i = 0; $i < 5; $i++) {
            $existingDonors[] = Donor::create([
                'firstName' => "Existing{$i}",
                'lastName' => 'Donor',
                'name' => "Existing{$i} Donor",
                'email' => "existing{$i}@test.com",
            ]);
        }

        $initialDonorCount = Donor::query()->count();

        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            3,
            'last_30_days',
            'test',
            'complete',
            'use_existing',
            0
        );

        $this->assertEquals(3, $generated);

        // Should not have created any new donors
        $finalDonorCount = Donor::query()->count();
        $this->assertEquals($initialDonorCount, $finalDonorCount);

        // Verify that all donations used existing donors
        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        $existingDonorIds = array_map(function($donor) {
            return $donor->id;
        }, $existingDonors);

        foreach ($donations as $donation) {
            $this->assertContains($donation->donorId, $existingDonorIds);
        }
    }

    /**
     * Test creating donations with mixed donor strategy.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsWithMixedDonors()
    {
        // Create some existing donors first
        $existingDonors = [];
        for ($i = 0; $i < 3; $i++) {
            $existingDonors[] = Donor::create([
                'firstName' => "Existing{$i}",
                'lastName' => 'Donor',
                'name' => "Existing{$i} Donor",
                'email' => "existing{$i}@test.com",
            ]);
        }

        $initialDonorCount = Donor::query()->count();

        // Generate more donations to increase chance of both new and existing being used
        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            10,
            'last_30_days',
            'test',
            'complete',
            'mixed',
            0
        );

        $this->assertEquals(10, $generated);

        // Should have some new donors created but not necessarily all
        $finalDonorCount = Donor::query()->count();
        $this->assertGreaterThanOrEqual($initialDonorCount, $finalDonorCount);

        // Verify donations were created
        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        $this->assertCount(10, $donations);
    }

    /**
     * Test fallback behavior when no existing donors are found.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsWithUseExistingButNoExistingDonors()
    {
        // Ensure no existing donors
        $this->assertEquals(0, Donor::query()->count());

        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            2,
            'last_30_days',
            'test',
            'complete',
            'use_existing',
            0
        );

        $this->assertEquals(2, $generated);

        // Should have created new donors as fallback
        $finalDonorCount = Donor::query()->count();
        $this->assertEquals(2, $finalDonorCount);

        // Verify donations were created
        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        $this->assertCount(2, $donations);
    }

    /**
     * Test that existing donor information is preserved in donations.
     *
     * @since 1.0.0
     */
    public function testExistingDonorInformationIsPreservedInDonations()
    {
        // Create an existing donor with specific information
        $existingDonor = Donor::create([
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'name' => 'Jane Smith',
            'email' => 'jane.smith@test.com',
            'phone' => '555-123-4567',
        ]);

        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            1,
            'last_30_days',
            'test',
            'complete',
            'use_existing',
            0
        );

        $this->assertEquals(1, $generated);

        // Get the created donation
        $donation = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->get();

        // Verify the donation uses the existing donor's information
        $this->assertEquals($existingDonor->id, $donation->donorId);
        $this->assertEquals('Jane', $donation->firstName);
        $this->assertEquals('Smith', $donation->lastName);
        $this->assertEquals('jane.smith@test.com', $donation->email);
        $this->assertEquals('555-123-4567', $donation->phone);
    }

    /**
     * Test creating donations with a specific selected donor.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsWithSelectSpecificDonor()
    {
        // Create a specific donor to use
        $specificDonor = Donor::create([
            'firstName' => 'Sarah',
            'lastName' => 'Connor',
            'name' => 'Sarah Connor',
            'email' => 'sarah.connor@test.com',
            'phone' => '555-987-6543',
        ]);

        // Create some other donors that should not be used
        $otherDonor = Donor::create([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'name' => 'John Doe',
            'email' => 'john.doe@test.com',
        ]);

        $generated = $this->generator->generateDonations(
            $this->testCampaign,
            3,
            'last_30_days',
            'test',
            'complete',
            'select_specific',
            $specificDonor->id
        );

        $this->assertEquals(3, $generated);

        // Get all created donations
        $donations = Donation::query()
            ->where('campaignId', $this->testCampaign->id)
            ->getAll();

        $this->assertCount(3, $donations);

        // Verify all donations use the specific donor
        foreach ($donations as $donation) {
            $this->assertEquals($specificDonor->id, $donation->donorId);
            $this->assertEquals('Sarah', $donation->firstName);
            $this->assertEquals('Connor', $donation->lastName);
            $this->assertEquals('sarah.connor@test.com', $donation->email);
            $this->assertEquals('555-987-6543', $donation->phone);

            // Ensure it's not using the other donor
            $this->assertNotEquals($otherDonor->id, $donation->donorId);
        }
    }

    /**
     * Test generating donations with select specific donor not found.
     *
     * @since 1.0.0
     */
    public function testGenerateDonationsWithSelectSpecificDonorNotFound()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The selected donor does not exist.');

        $this->generator->generateDonations(
            $this->testCampaign,
            2,
            'last_30_days',
            'test',
            'complete',
            'select_specific',
            999999 // Non-existent donor ID
        );
    }

    /**
     * Test bulk donation generation across multiple campaigns.
     *
     * @since 1.0.0
     */
    public function testGenerateBulkDonationsAcrossMultipleCampaigns()
    {
        // Create additional test campaigns
        $campaign2 = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Test Campaign 2',
            'shortDescription' => 'Test campaign 2 description',
            'longDescription' => 'Test campaign 2 long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 200000, // $2000
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);

        $campaign3 = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Test Campaign 3',
            'shortDescription' => 'Test campaign 3 description',
            'longDescription' => 'Test campaign 3 long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 300000, // $3000
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);

        $campaigns = [$this->testCampaign, $campaign2, $campaign3];
        $donationsPerCampaign = 3;

        $totalGenerated = $this->generator->generateBulkDonations(
            $campaigns,
            $donationsPerCampaign,
            'last_30_days',
            'test',
            'complete',
            'create_new',
            0
        );

        // Should generate 3 donations per campaign across 3 campaigns = 9 total
        $expectedTotal = count($campaigns) * $donationsPerCampaign;
        $this->assertEquals($expectedTotal, $totalGenerated);

        // Verify donations were created for each campaign
        foreach ($campaigns as $campaign) {
            $donations = Donation::query()
                ->where('campaignId', $campaign->id)
                ->getAll();

            $this->assertCount($donationsPerCampaign, $donations, "Campaign {$campaign->title} should have {$donationsPerCampaign} donations");

            // Verify all donations are in test mode and complete status
            foreach ($donations as $donation) {
                $this->assertEquals('test', $donation->mode->getValue());
                $this->assertEquals('COMPLETE', $donation->status->getValue());
            }
        }
    }

    /**
     * Test bulk donation generation with specific donor.
     *
     * @since 1.0.0
     */
    public function testGenerateBulkDonationsWithSpecificDonor()
    {
        // Create a test donor
        $testDonor = Donor::create([
            'firstName' => 'Test',
            'lastName' => 'Donor',
            'name' => 'Test Donor',
            'email' => 'test@example.com',
        ]);

        // Create additional test campaign
        $campaign2 = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Test Campaign 2',
            'shortDescription' => 'Test campaign 2 description',
            'longDescription' => 'Test campaign 2 long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 200000, // $2000
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);

        $campaigns = [$this->testCampaign, $campaign2];
        $donationsPerCampaign = 2;

        $totalGenerated = $this->generator->generateBulkDonations(
            $campaigns,
            $donationsPerCampaign,
            'last_30_days',
            'test',
            'complete',
            'select_specific',
            $testDonor->id
        );

        // Should generate 2 donations per campaign across 2 campaigns = 4 total
        $expectedTotal = count($campaigns) * $donationsPerCampaign;
        $this->assertEquals($expectedTotal, $totalGenerated);

        // Verify all donations are associated with the specific donor
        $allDonations = Donation::query()->getAll();
        foreach ($allDonations as $donation) {
            $this->assertEquals($testDonor->id, $donation->donorId);
            $this->assertEquals($testDonor->email, $donation->email);
        }
    }

    /**
     * Test bulk donation generation with custom date range.
     *
     * @since 1.0.0
     */
    public function testGenerateBulkDonationsWithCustomDateRange()
    {
        // Create additional test campaign
        $campaign2 = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Test Campaign 2',
            'shortDescription' => 'Test campaign 2 description',
            'longDescription' => 'Test campaign 2 long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 200000, // $2000
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);

        $campaigns = [$this->testCampaign, $campaign2];
        $donationsPerCampaign = 2;
        $startDate = '2024-01-01';
        $endDate = '2024-01-31';

        $totalGenerated = $this->generator->generateBulkDonations(
            $campaigns,
            $donationsPerCampaign,
            'custom',
            'test',
            'complete',
            'create_new',
            0,
            $startDate,
            $endDate
        );

        // Should generate 2 donations per campaign across 2 campaigns = 4 total
        $expectedTotal = count($campaigns) * $donationsPerCampaign;
        $this->assertEquals($expectedTotal, $totalGenerated);

        // Verify all donations are within the specified date range
        $allDonations = Donation::query()->getAll();
        foreach ($allDonations as $donation) {
            $createdAt = new DateTime($donation->createdAt);
            $this->assertGreaterThanOrEqual(new DateTime($startDate), $createdAt);
            $this->assertLessThanOrEqual(new DateTime($endDate), $createdAt);
        }
    }

    /**
     * Create an admin user for testing.
     *
     * @since 1.0.0
     * @return \WP_User
     */
    private function createAdminUser()
    {
        return $this->factory()->user->create_and_get([
            'role' => 'administrator'
        ]);
    }

    /**
     * Create a subscriber user for testing.
     *
     * @since 1.0.0
     * @return \WP_User
     */
    private function createSubscriberUser()
    {
        return $this->factory()->user->create_and_get([
            'role' => 'subscriber'
        ]);
    }
}
