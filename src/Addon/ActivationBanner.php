<?php

namespace GiveDataGenerator\Addon;

use Give_Addon_Activation_Banner;

/**
 * Helper class responsible for showing add-on Activation Banner.
 *
 * @package     GiveDataGenerator\Addon\Helpers
 * @copyright   Copyright (c) 2020, GiveWP
 */
class ActivationBanner
{

    /**
     * Show activation banner
     *
     * @since 1.0.0
     * @return void
     */
    public function show()
    {
        // Check for Activation banner class.
        if ( ! class_exists('Give_Addon_Activation_Banner')) {
            include GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php';
        }

        // Only runs on admin.
        $args = [
            'file' => GIVE_DATA_GENERATOR_FILE,
            'name' => GIVE_DATA_GENERATOR_NAME,
            'version' => GIVE_DATA_GENERATOR_VERSION,
            'settings_url' => admin_url('edit.php?post_type=give_forms&page=data-generator'),
            'documentation_url' => 'https://givewp.com/documentation',
            'support_url' => 'https://givewp.com/support/',
            'testing' => false, // Never leave true.
        ];

        new Give_Addon_Activation_Banner($args);
    }
}
