<?php

namespace GiveFaker\Tests\Unit\GiveFaker;

use Give\Tests\TestCase;
use Give\Tests\TestTraits\RefreshDatabase;
use GiveFaker\TestDonationGenerator\ServiceProvider;

class TestServiceProvider extends TestCase
{
    use RefreshDatabase;

    /**
     * @var ServiceProvider
     */
    private $serviceProvider;

    /**
     * Set up test environment.
     *
     * @since 1.0.0
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->serviceProvider = new ServiceProvider();
    }

    /**
     * Test service provider registration.
     *
     * @since 1.0.0
     */
    public function testServiceProviderRegistration()
    {
        // Test that the service provider can be instantiated
        $this->assertInstanceOf(ServiceProvider::class, $this->serviceProvider);
    }

    /**
     * Test service provider boot method.
     *
     * @since 1.0.0
     */
    public function testServiceProviderBoot()
    {
        // Boot the service provider
        $this->serviceProvider->boot();

        // Test that hooks are registered
        $this->assertTrue(has_action('admin_menu'));
        $this->assertTrue(has_action('wp_ajax_generate_test_donations'));
        $this->assertTrue(has_action('admin_enqueue_scripts'));
    }

    /**
     * Test admin menu hook registration.
     *
     * @since 1.0.0
     */
    public function testAdminMenuHookRegistration()
    {
        $this->serviceProvider->boot();

        // Check that admin_menu hook is registered
        $this->assertGreaterThan(0, has_action('admin_menu'));
    }

    /**
     * Test AJAX hook registration.
     *
     * @since 1.0.0
     */
    public function testAjaxHookRegistration()
    {
        $this->serviceProvider->boot();

        // Check that AJAX hook is registered (only for logged-in users)
        $this->assertGreaterThan(0, has_action('wp_ajax_generate_test_donations'));

        // We don't register nopriv hook since this is admin-only functionality
        $this->assertFalse(has_action('wp_ajax_nopriv_generate_test_donations'));
    }

    /**
     * Test script enqueue hook registration.
     *
     * @since 1.0.0
     */
    public function testScriptEnqueueHookRegistration()
    {
        $this->serviceProvider->boot();

        // Check that admin_enqueue_scripts hook is registered
        $this->assertGreaterThan(0, has_action('admin_enqueue_scripts'));
    }

    /**
     * Test script enqueuing on correct page.
     *
     * @since 1.0.0
     */
    public function testScriptEnqueuingOnCorrectPage()
    {
        global $pagenow;
        $pagenow = 'edit.php';
        $_GET['post_type'] = 'give_forms';
        $_GET['page'] = 'test-donation-generator';

        // Set up admin user
        $user = $this->factory()->user->create_and_get([
            'role' => 'administrator'
        ]);
        wp_set_current_user($user->ID);

        // Boot the service provider
        $this->serviceProvider->boot();

        // Simulate enqueue_scripts action
        ob_start();
        do_action('admin_enqueue_scripts', 'give_forms_page_test-donation-generator');
        $output = ob_get_clean();

        // Verify script contains our object with AJAX URL
        $this->assertStringContainsString('testDonationGenerator', $output);
        $this->assertStringContainsString(admin_url('admin-ajax.php'), $output);
        $this->assertStringContainsString('wp_create_nonce', $output);
    }

    /**
     * Test that service provider doesn't interfere with other pages.
     *
     * @since 1.0.0
     */
    public function testServiceProviderDoesntInterfereWithOtherPages()
    {
        global $pagenow;
        $pagenow = 'index.php'; // Dashboard page

        // Boot the service provider
        $this->serviceProvider->boot();

        // Simulate enqueue_scripts action on wrong page
        ob_start();
        do_action('admin_enqueue_scripts', 'dashboard');
        $output = ob_get_clean();

        // Should not contain our script
        $this->assertStringNotContainsString('testDonationGenerator', $output);
    }

    /**
     * Test multiple service provider instances.
     *
     * @since 1.0.0
     */
    public function testMultipleServiceProviderInstances()
    {
        $provider1 = new ServiceProvider();
        $provider2 = new ServiceProvider();

        // Both should be valid instances
        $this->assertInstanceOf(ServiceProvider::class, $provider1);
        $this->assertInstanceOf(ServiceProvider::class, $provider2);

        // Boot both
        $provider1->boot();
        $provider2->boot();

        // Hooks should still be registered properly
        $this->assertTrue(has_action('admin_menu'));
        $this->assertTrue(has_action('wp_ajax_generate_test_donations'));
    }
}
