<?php

namespace GiveFaker\Addon;

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
            GIVE_FAKER_FILE,
            GIVE_FAKER_NAME,
            GIVE_FAKER_VERSION,
            'GiveWP'
        );
    }
}
