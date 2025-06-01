<?php

namespace GiveDataGenerator\DataGenerator;

use Give\Helpers\Hooks;
use Give\ServiceProviders\ServiceProvider as ServiceProviderInterface;
use GiveDataGenerator\DataGenerator\AdminSettings;
use GiveDataGenerator\DataGenerator\DonationGenerator;
use GiveDataGenerator\DataGenerator\CampaignGenerator;
use GiveDataGenerator\DataGenerator\DonationFormGenerator;
use GiveDataGenerator\DataGenerator\SubscriptionGenerator;
use GiveDataGenerator\DataGenerator\CleanUpManager;

/**
 * Service provider for the Data Generator domain.
 *
 * @package     GiveDataGenerator\DataGenerator
 * @since       1.0.0
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        give()->singleton(DonationGenerator::class);
        give()->singleton(CampaignGenerator::class);
        give()->singleton(DonationFormGenerator::class);
        give()->singleton(SubscriptionGenerator::class);
        give()->singleton(CleanUpManager::class);
        give()->singleton(AdminSettings::class);
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        $this->registerAdminSettings();
        $this->registerAjaxHandlers();
    }

    /**
     * Register admin settings.
     *
     * @since 1.0.0
     */
    private function registerAdminSettings(): void
    {
        $adminSettings = give(AdminSettings::class);
        $adminSettings->addAdminMenu();
        add_action('admin_enqueue_scripts', [$adminSettings, 'enqueueAdminScripts']);
    }

    /**
     * Register AJAX handlers.
     *
     * @since 1.0.0
     */
    private function registerAjaxHandlers(): void
    {
        // Handle AJAX requests
        Hooks::addAction('wp_ajax_generate_test_donations', DonationGenerator::class, 'handleAjaxRequest');
        Hooks::addAction('wp_ajax_generate_test_campaigns', CampaignGenerator::class, 'handleAjaxRequest');
        Hooks::addAction('wp_ajax_generate_test_donation_forms', DonationFormGenerator::class, 'handleAjaxRequest');
        Hooks::addAction('wp_ajax_generate_test_subscriptions', SubscriptionGenerator::class, 'handleAjaxRequest');
        Hooks::addAction('wp_ajax_cleanup_test_data', CleanUpManager::class, 'handleAjaxRequest');
    }
}
