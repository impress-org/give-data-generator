<?php

namespace GiveFaker\Tests\Unit\GiveFaker;

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
use GiveFaker\TestDonationGenerator\DonationGenerator;

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
            'test'
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
            'test'
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
            'live'
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
            'test'
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
            'test'
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
            'test'
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
            'test'
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
