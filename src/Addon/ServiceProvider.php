<?php

namespace GiveFaker\Addon;

use Give\Helpers\Hooks;
use GiveFaker\Addon\Activation;
use GiveFaker\Addon\ActivationBanner;
use GiveFaker\Addon\Language;
use GiveFaker\Addon\License;
use GiveFaker\Addon\Links;
use Give\ServiceProviders\ServiceProvider as ServiceProviderInterface;

/**
 * Example of a service provider responsible for add-on initialization.
 *
 * @package     GiveFaker\Addon
 * @copyright   Copyright (c) 2020, GiveWP
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        give()->singleton(Activation::class);
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        // Load add-on translations.
        Hooks::addAction('init', Language::class, 'load');
        // Load add-on links.
        Hooks::addFilter('plugin_action_links_' . GIVE_FAKER_BASENAME, Links::class);

        Hooks::addAction('admin_init', License::class, 'check');
        Hooks::addAction('admin_init', ActivationBanner::class, 'show', 20);
    }
}
