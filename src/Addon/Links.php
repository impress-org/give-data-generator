<?php

namespace GiveDataGenerator\Addon;

class Links
{
    /**
     * Add settings link
     * @return array
     * @since 1.0.0
     */
    public function __invoke($actions)
    {
        $newActions = array(
            'settings' => sprintf(
                '<a href="%1$s">%2$s</a>',
                admin_url('edit.php?post_type=give_forms&page=data-generator'),
                __('Settings', 'give-data-generator')
            ),
        );

        return array_merge($newActions, $actions);
    }
}
