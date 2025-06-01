<?php

namespace GiveDataGenerator\Tests\Unit\GiveDataGenerator;

use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignGoalType;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Campaigns\ValueObjects\CampaignType;
use Give\Tests\TestCase;
use Give\Tests\TestTraits\RefreshDatabase;
use GiveDataGenerator\DataGenerator\AdminSettings;

class TestAdminSettings extends TestCase
{
    use RefreshDatabase;

    /**
     * @var AdminSettings
     */
    private $adminSettings;

    /**
     * Set up test environment.
     *
     * @since 1.0.0
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->adminSettings = new AdminSettings();
    }

    /**
     * Test admin menu is added correctly.
     *
     * @since 1.0.0
     */
    public function testAdminMenuIsAdded()
    {
        // Set up an admin user to ensure proper permissions
        $user = $this->factory()->user->create_and_get([
            'role' => 'administrator'
        ]);
        wp_set_current_user($user->ID);

        // Test that the method executes without errors
        $result = $this->adminSettings->addAdminMenu();

        // The method should execute successfully (no return value, but no exceptions)
        $this->assertNull($result);

        // Test that the method is callable multiple times without errors
        $this->adminSettings->addAdminMenu();
        $this->assertTrue(true); // If we get here, no fatal errors occurred
    }

    /**
     * Test that campaigns are retrieved correctly.
     *
     * @since 1.0.0
     */
    public function testGetCampaignsRetrievesCorrectly()
    {
        // Create test campaigns
        $campaign1 = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Test Campaign 1',
            'shortDescription' => 'Test campaign description',
            'longDescription' => 'Test campaign long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 100000,
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);

        $campaign2 = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Test Campaign 2',
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

        // Use reflection to call private getCampaigns method
        $reflection = new \ReflectionClass($this->adminSettings);
        $method = $reflection->getMethod('getCampaigns');
        $method->setAccessible(true);

        $campaigns = $method->invoke($this->adminSettings);

        $this->assertCount(2, $campaigns);

        // Check that our campaigns are in the results
        $campaignIds = wp_list_pluck($campaigns, 'id');

        // Verify our campaigns are in the list
        $this->assertContains($campaign1->id, $campaignIds);
        $this->assertContains($campaign2->id, $campaignIds);
    }

    /**
     * Test that admin page renders without errors.
     *
     * @since 1.0.0
     */
    public function testAdminPageRenders()
    {
        // Create a test campaign
        Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Test Campaign',
            'shortDescription' => 'Test campaign description',
            'longDescription' => 'Test campaign long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 100000,
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);

        // Set up admin user
        $user = $this->factory()->user->create_and_get([
            'role' => 'administrator'
        ]);
        wp_set_current_user($user->ID);

        // Capture output
        ob_start();
        $this->adminSettings->renderAdminPage();
        $output = ob_get_clean();

        // Basic checks that the page rendered
        $this->assertStringContainsString('Data Generator', $output);
        $this->assertStringContainsString('campaign_id', $output);
        $this->assertStringContainsString('donation_count', $output);
        $this->assertStringContainsString('date_range', $output);
        $this->assertStringContainsString('donation_mode', $output);
        $this->assertStringContainsString('Generate Test Data', $output);
    }

    /**
     * Test that form fields are present in rendered page.
     *
     * @since 1.0.0
     */
    public function testFormFieldsArePresent()
    {
        // Create a test campaign
        Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Test Campaign',
            'shortDescription' => 'Test campaign description',
            'longDescription' => 'Test campaign long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 100000,
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);

        ob_start();
        $this->adminSettings->renderAdminPage();
        $output = ob_get_clean();

        // Check for specific form elements
        $this->assertStringContainsString('select name="campaign_id"', $output);
        $this->assertStringContainsString('input type="number" name="donation_count"', $output);
        $this->assertStringContainsString('select name="date_range"', $output);
        $this->assertStringContainsString('select name="donation_mode"', $output);
        $this->assertStringContainsString('input type="date" name="start_date"', $output);
        $this->assertStringContainsString('input type="date" name="end_date"', $output);
    }

    /**
     * Test that date range options are present.
     *
     * @since 1.0.0
     */
    public function testDateRangeOptionsArePresent()
    {
        ob_start();
        $this->adminSettings->renderAdminPage();
        $output = ob_get_clean();

        // Check for date range options
        $this->assertStringContainsString('Last 30 Days', $output);
        $this->assertStringContainsString('Last 90 Days', $output);
        $this->assertStringContainsString('Last Year', $output);
        $this->assertStringContainsString('Custom Range', $output);
    }

    /**
     * Test that donation mode options are present.
     *
     * @since 1.0.0
     */
    public function testDonationModeOptionsArePresent()
    {
        ob_start();
        $this->adminSettings->renderAdminPage();
        $output = ob_get_clean();

        // Check for donation mode options
        $this->assertStringContainsString('Test Mode', $output);
        $this->assertStringContainsString('Live Mode', $output);
    }

    /**
     * Test that JavaScript dependencies are set up correctly.
     *
     * @since 1.0.0
     */
    public function testJavaScriptDependenciesAreSetUp()
    {
        ob_start();
        $this->adminSettings->renderAdminPage();
        $output = ob_get_clean();

        // Check that the form has the necessary IDs and classes for JavaScript to work
        $this->assertStringContainsString('id="donation-generator-form"', $output);
        $this->assertStringContainsString('id="date_range"', $output);
        $this->assertStringContainsString('id="custom-date-range"', $output);
        $this->assertStringContainsString('id="generate-donations"', $output);
        $this->assertStringContainsString('class="spinner"', $output);
        $this->assertStringContainsString('id="generation-results"', $output);

        // Check that tab-specific JavaScript functionality is included
        $this->assertStringContainsString('<script>', $output);
        $this->assertStringContainsString('DOMContentLoaded', $output);
        $this->assertStringContainsString('date_range', $output);
        $this->assertStringContainsString('custom-date-range', $output);
    }

    /**
     * Test that nonce field is included.
     *
     * @since 1.0.0
     */
    public function testNonceFieldIsIncluded()
    {
        ob_start();
        $this->adminSettings->renderAdminPage();
        $output = ob_get_clean();

        // Check for nonce field
        $this->assertStringContainsString('data_generator_nonce', $output);
    }

    /**
     * Test that campaigns are listed in select dropdown.
     *
     * @since 1.0.0
     */
    public function testCampaignsAreListedInDropdown()
    {
        // Create test campaigns
        $campaign1 = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'First Campaign',
            'shortDescription' => 'Test campaign description',
            'longDescription' => 'Test campaign long description',
            'logo' => '',
            'image' => '',
            'primaryColor' => '#28C77B',
            'secondaryColor' => '#FFA200',
            'goal' => 100000,
            'goalType' => CampaignGoalType::AMOUNT(),
            'status' => CampaignStatus::ACTIVE()
        ]);

        $campaign2 = Campaign::create([
            'type' => CampaignType::CORE(),
            'title' => 'Second Campaign',
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

        ob_start();
        $this->adminSettings->renderAdminPage();
        $output = ob_get_clean();

        // Check that campaigns appear in the dropdown
        $this->assertStringContainsString('First Campaign', $output);
        $this->assertStringContainsString('Second Campaign', $output);
        $this->assertStringContainsString('value="' . $campaign1->id . '"', $output);
        $this->assertStringContainsString('value="' . $campaign2->id . '"', $output);
    }

    /**
     * Test that empty campaigns list doesn't break rendering.
     *
     * @since 1.0.0
     */
    public function testEmptyCampaignsListDoesntBreakRendering()
    {
        // Don't create any campaigns

        ob_start();
        $this->adminSettings->renderAdminPage();
        $output = ob_get_clean();

        // Should still render the form
        $this->assertStringContainsString('Data Generator', $output);
        $this->assertStringContainsString('Select a Campaign', $output);
        $this->assertStringContainsString('Generate Test Data', $output);
    }

    /**
     * Test that proper CSS classes are applied.
     *
     * @since 1.0.0
     */
    public function testProperCssClassesAreApplied()
    {
        ob_start();
        $this->adminSettings->renderAdminPage();
        $output = ob_get_clean();

        // Check for WordPress admin CSS classes
        $this->assertStringContainsString('class="wrap"', $output);
        $this->assertStringContainsString('class="form-table"', $output);
        $this->assertStringContainsString('class="regular-text"', $output);
        $this->assertStringContainsString('class="button button-primary"', $output);
        $this->assertStringContainsString('class="spinner"', $output);
    }
}
