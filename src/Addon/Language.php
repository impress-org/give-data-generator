<?php

namespace GiveFaker\Addon;

/**
 * Helper class responsible for loading add-on translations.
 *
 * @package     GiveFaker\Addon\Helpers
 * @copyright   Copyright (c) 2020, GiveWP
 */
class Language
{
    /**
     * Load language.
     *
     * @since 1.0.0
     * @return void
     */
    public static function load()
    {
        // Set filter for plugin's languages directory.
        $langDir = apply_filters(
            sprintf('%s_languages_directory', 'give-data-generator'),
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.ValidHookName.NotLowercase
            dirname(GIVE_FAKER_BASENAME) . '/languages/'
        );

        // Traditional WordPress plugin locale filter.
        $locale = apply_filters('plugin_locale', get_locale(), 'give-data-generator');
        $moFile = sprintf('%1$s-%2$s.mo', 'give-data-generator', $locale);

        // Setup paths to current locale file.
        $moFileLocal = $langDir . $moFile;
        $moFileGlobal = WP_LANG_DIR . 'give-data-generator' . $moFile;

        if (file_exists($moFileGlobal)) {
            // Look in global /wp-content/languages/TEXTDOMAIN folder.
            load_textdomain('give-data-generator', $moFileGlobal);
        } elseif (file_exists($moFileLocal)) {
            // Look in local /wp-content/plugins/TEXTDOMAIN/languages/ folder.
            load_textdomain('give-data-generator', $moFileLocal);
        } else {
            // Load the default language files.
            load_plugin_textdomain('give-data-generator', false, $langDir);
        }
    }
}
