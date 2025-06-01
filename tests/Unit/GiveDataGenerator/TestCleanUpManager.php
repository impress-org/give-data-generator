<?php

namespace GiveDataGenerator\Tests\Unit\GiveDataGenerator;

use Give\Tests\TestCase;
use Give\Tests\TestTraits\RefreshDatabase;
use Give\Donations\Models\Donation;
use Give\Subscriptions\Models\Subscription;
use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Donations\ValueObjects\DonationMode;
use Give\Subscriptions\ValueObjects\SubscriptionMode;
use Give\Donors\Models\Donor;
use Give\DonationForms\Models\DonationForm;
use GiveDataGenerator\DataGenerator\CleanUpManager;
use GiveDataGenerator\DataGenerator\DonationGenerator;
use GiveDataGenerator\DataGenerator\SubscriptionGenerator;
use GiveDataGenerator\DataGenerator\CampaignGenerator;

class TestCleanUpManager extends TestCase
{
    use RefreshDatabase;

    /**
     * @var CleanUpManager
     */
    private $cleanUpManager;

    /**
     * @var DonationGenerator
     */
    private $donationGenerator;

    /**
     * @var SubscriptionGenerator
     */
    private $subscriptionGenerator;

    /**
     * @var CampaignGenerator
     */
    private $campaignGenerator;

    /**
     * Set up test environment.
     *
     * @since 1.0.0
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->cleanUpManager = new CleanUpManager();
        $this->donationGenerator = new DonationGenerator();
        $this->subscriptionGenerator = new SubscriptionGenerator();
        $this->campaignGenerator = new CampaignGenerator();

        // Set up admin user for permissions
        $user = $this->factory()->user->create_and_get([
            'role' => 'administrator'
        ]);
        wp_set_current_user($user->ID);
    }

    /**
     * Test CleanUpManager instantiation.
     *
     * @since 1.0.0
     */
    public function testCleanUpManagerInstantiation()
    {
        $this->assertInstanceOf(CleanUpManager::class, $this->cleanUpManager);
    }

    /**
     * Test deleteTestDonations with no test donations.
     *
     * @since 1.0.0
     */
    public function testDeleteTestDonationsWhenNoneExist()
    {
        // Create a live mode donation to ensure it's not deleted
        $liveDonation = Donation::factory()->create(['mode' => DonationMode::LIVE()]);

        // Call the private method using reflection
        $result = $this->callPrivateMethod($this->cleanUpManager, 'deleteTestDonations');

        $this->assertEquals(0, $result['deleted_count']);
        $this->assertStringContainsString('0 test donations deleted', $result['message']);
        $this->assertEmpty($result['errors']);

        // Ensure live donation still exists
        $this->assertInstanceOf(Donation::class, Donation::find($liveDonation->id));
    }

    /**
     * Test deleteTestDonations with test donations.
     *
     * @since 1.0.0
     */
    public function testDeleteTestDonationsWhenTestDonationsExist()
    {
        // Create some test donations
        $testDonation1 = Donation::factory()->create(['mode' => DonationMode::TEST()]);
        $testDonation2 = Donation::factory()->create(['mode' => DonationMode::TEST()]);
        $liveDonation = Donation::factory()->create(['mode' => DonationMode::LIVE()]);

        // Verify they exist
        $this->assertInstanceOf(Donation::class, Donation::find($testDonation1->id));
        $this->assertInstanceOf(Donation::class, Donation::find($testDonation2->id));
        $this->assertInstanceOf(Donation::class, Donation::find($liveDonation->id));

        // Call the cleanup method
        $result = $this->callPrivateMethod($this->cleanUpManager, 'deleteTestDonations');

        $this->assertEquals(2, $result['deleted_count']);
        $this->assertStringContainsString('2 test donations deleted', $result['message']);
        $this->assertEmpty($result['errors']);

        // Verify test donations are deleted but live donation remains
        $this->assertNull(Donation::find($testDonation1->id));
        $this->assertNull(Donation::find($testDonation2->id));
        $this->assertInstanceOf(Donation::class, Donation::find($liveDonation->id));
    }

    /**
     * Test deleteTestSubscriptions with no test subscriptions.
     *
     * @since 1.0.0
     */
    public function testDeleteTestSubscriptionsWhenNoneExist()
    {
        // Create a live mode subscription to ensure it's not deleted
        $liveSubscription = Subscription::factory()->create(['mode' => SubscriptionMode::LIVE()]);

        // Call the private method
        $result = $this->callPrivateMethod($this->cleanUpManager, 'deleteTestSubscriptions');

        $this->assertEquals(0, $result['deleted_count']);
        $this->assertStringContainsString('0 test subscriptions deleted', $result['message']);
        $this->assertEmpty($result['errors']);

        // Ensure live subscription still exists
        $this->assertInstanceOf(Subscription::class, Subscription::find($liveSubscription->id));
    }

    /**
     * Test deleteTestSubscriptions with test subscriptions.
     *
     * @since 1.0.0
     */
    public function testDeleteTestSubscriptionsWhenTestSubscriptionsExist()
    {
        // Create some test subscriptions
        $testSubscription1 = Subscription::factory()->create(['mode' => SubscriptionMode::TEST()]);
        $testSubscription2 = Subscription::factory()->create(['mode' => SubscriptionMode::TEST()]);
        $liveSubscription = Subscription::factory()->create(['mode' => SubscriptionMode::LIVE()]);

        // Verify they exist
        $this->assertInstanceOf(Subscription::class, Subscription::find($testSubscription1->id));
        $this->assertInstanceOf(Subscription::class, Subscription::find($testSubscription2->id));

        // Call the cleanup method
        $result = $this->callPrivateMethod($this->cleanUpManager, 'deleteTestSubscriptions');

        $this->assertEquals(2, $result['deleted_count']);
        $this->assertStringContainsString('2 test subscriptions deleted', $result['message']);
        $this->assertEmpty($result['errors']);

        // Verify test subscriptions are deleted
        $this->assertNull(Subscription::find($testSubscription1->id));
        $this->assertNull(Subscription::find($testSubscription2->id));

        // Verify live subscription still exists
        $this->assertInstanceOf(Subscription::class, Subscription::find($liveSubscription->id));
    }

    /**
     * Test archiveCampaigns with no active campaigns.
     *
     * @since 1.0.0
     */
    public function testArchiveCampaignsWhenNoneActive()
    {
        // Create a draft campaign to ensure it's not affected
        $draftCampaign = Campaign::factory()->create(['status' => CampaignStatus::DRAFT()]);

        // Call the private method
        $result = $this->callPrivateMethod($this->cleanUpManager, 'archiveCampaigns');

        $this->assertEquals(0, $result['archived_count']);
        $this->assertStringContainsString('0 campaigns archived', $result['message']);
        $this->assertEmpty($result['errors']);

        // Ensure draft campaign is unchanged
        $updatedCampaign = Campaign::find($draftCampaign->id);
        $this->assertTrue($updatedCampaign->status->isDraft());
    }

    /**
     * Test archiveCampaigns with active campaigns.
     *
     * @since 1.0.0
     */
    public function testArchiveCampaignsWhenActiveCampaignsExist()
    {
        // Create some active campaigns
        $activeCampaign1 = Campaign::factory()->create(['status' => CampaignStatus::ACTIVE()]);
        $activeCampaign2 = Campaign::factory()->create(['status' => CampaignStatus::ACTIVE()]);
        $draftCampaign = Campaign::factory()->create(['status' => CampaignStatus::DRAFT()]);

        // Verify initial states
        $this->assertTrue(Campaign::find($activeCampaign1->id)->status->isActive());
        $this->assertTrue(Campaign::find($activeCampaign2->id)->status->isActive());
        $this->assertTrue(Campaign::find($draftCampaign->id)->status->isDraft());

        // Call the cleanup method
        $result = $this->callPrivateMethod($this->cleanUpManager, 'archiveCampaigns');

        $this->assertEquals(2, $result['archived_count']);
        $this->assertStringContainsString('2 campaigns archived', $result['message']);
        $this->assertEmpty($result['errors']);

        // Verify active campaigns are now archived
        $this->assertTrue(Campaign::find($activeCampaign1->id)->status->isArchived());
        $this->assertTrue(Campaign::find($activeCampaign2->id)->status->isArchived());

        // Verify draft campaign is unchanged
        $this->assertTrue(Campaign::find($draftCampaign->id)->status->isDraft());
    }

    /**
     * Test AJAX request handling with invalid nonce.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestWithInvalidNonce()
    {
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['action_type'] = 'delete_test_donations';

        $this->expectOutputString('{"success":false,"data":{"message":"Security check failed."}}');
        $this->cleanUpManager->handleAjaxRequest();
    }

    /**
     * Test AJAX request handling with insufficient permissions.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestWithInsufficientPermissions()
    {
        // Set up subscriber user
        $user = $this->factory()->user->create_and_get(['role' => 'subscriber']);
        wp_set_current_user($user->ID);

        $_POST['nonce'] = wp_create_nonce('cleanup_nonce');
        $_POST['action_type'] = 'delete_test_donations';

        $this->expectOutputString('{"success":false,"data":{"message":"You do not have permission to perform this action."}}');
        $this->cleanUpManager->handleAjaxRequest();
    }

    /**
     * Test AJAX request handling with invalid action type.
     *
     * @since 1.0.0
     */
    public function testAjaxRequestWithInvalidActionType()
    {
        $_POST['nonce'] = wp_create_nonce('cleanup_nonce');
        $_POST['action_type'] = 'invalid_action';

        $this->expectOutputString('{"success":false,"data":{"message":"Invalid action type."}}');
        $this->cleanUpManager->handleAjaxRequest();
    }

    /**
     * Test successful AJAX request for deleting test donations.
     *
     * @since 1.0.0
     */
    public function testSuccessfulAjaxRequestForDeletingTestDonations()
    {
        // Create test donations
        Donation::factory()->create(['mode' => 'test']);
        Donation::factory()->create(['mode' => 'test']);

        $_POST['nonce'] = wp_create_nonce('cleanup_nonce');
        $_POST['action_type'] = 'delete_test_donations';

        ob_start();
        $this->cleanUpManager->handleAjaxRequest();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals(2, $response['data']['deleted_count']);
        $this->assertStringContainsString('2 test donations deleted', $response['data']['message']);
    }

    /**
     * Test database table structure for debugging.
     *
     * @since 1.0.0
     */
    public function testDatabaseTableStructure()
    {
        global $wpdb;

        // Check if required tables exist
        $donationTableExists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->prefix . 'give_donations'
        ));
        $this->assertNotEmpty($donationTableExists, 'Donations table should exist');

        $subscriptionTableExists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->prefix . 'give_subscriptions'
        ));
        $this->assertNotEmpty($subscriptionTableExists, 'Subscriptions table should exist');

        $campaignTableExists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->prefix . 'give_campaigns'
        ));
        $this->assertNotEmpty($campaignTableExists, 'Campaigns table should exist');
    }

    /**
     * Test integration with actual data generators.
     *
     * @since 1.0.0
     */
    public function testIntegrationWithDataGenerators()
    {
        // Generate test data using the data generators
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::ACTIVE()]);

        // Generate test donations with proper string parameters
        $generatedDonations = $this->donationGenerator->generateDonations(
            $campaign->id,
            3,
            'create_new',
            '',
            'last_30_days',
            'test',
            'complete',
            '',
            ''
        );
        $this->assertEquals(3, $generatedDonations);

        // Verify test donations exist
        $testDonations = Donation::query()->where('mode', DonationMode::TEST())->getAll();
        $this->assertCount(3, $testDonations);

        // Clean up test donations
        $result = $this->callPrivateMethod($this->cleanUpManager, 'deleteTestDonations');
        $this->assertEquals(3, $result['deleted_count']);

        // Verify they're gone
        $remainingTestDonations = Donation::query()->where('mode', DonationMode::TEST())->getAll();
        $this->assertEmpty($remainingTestDonations);
    }

    /**
     * Helper method to call private methods for testing.
     *
     * @since 1.0.0
     * @param object $object
     * @param string $methodName
     * @param array $args
     * @return mixed
     */
    private function callPrivateMethod($object, $methodName, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    /**
     * Tear down test environment.
     *
     * @since 1.0.0
     */
    public function tearDown(): void
    {
        // Clean up $_POST data
        $_POST = [];

        parent::tearDown();
    }
}

