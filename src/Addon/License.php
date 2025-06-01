<?php

namespace GiveDataGenerator\Addon;

use Give_License;

class License
{

    /**
     * Check add-on license.
     *
     * @since 1.0.0
     * @return void
     */
    public function check()
    {
        new Give_License(
            GIVE_DATA_GENERATOR_FILE,
            GIVE_DATA_GENERATOR_NAME,
            GIVE_DATA_GENERATOR_VERSION,
            'GiveWP'
        );
    }
}
