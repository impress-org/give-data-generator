<?php

namespace GiveDataGenerator\Addon;

/**
 * Helper class responsible for showing add-on notices.
 *
 * @package     GiveDataGenerator\Addon\Helpers
 * @copyright   Copyright (c) 2020, GiveWP
 */
class Notices
{

    /**
     * GiveWP min required version notice.
     *
     * @since 1.0.0
     * @return void
     */
    public static function giveVersionError()
    {
        Give()->notices->register_notice(
            [
                'id' => 'give-data-generator-activation-error',
                'type' => 'error',
                'description' => View::load('admin/notices/give-version-error'),
                'show' => true,
            ]
        );
    }

    /**
     * GiveWP inactive notice.
     *
     * @since 1.0.0
     * @return void
     */
    public static function giveInactive()
    {
        echo View::load('admin/notices/give-inactive');
    }
}
