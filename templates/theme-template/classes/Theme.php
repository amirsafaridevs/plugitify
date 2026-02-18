<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core theme class.
 */
class Sample_Theme_Core {

    public static function sample_theme_init() {
        add_action('init', [__CLASS__, 'sample_theme_register_hooks']);
    }

    public static function sample_theme_register_hooks() {
        // Sample_Theme hooks
    }

    public static function sample_theme_get_version() {
        return SAMPLE_THEME_VERSION;
    }

    public static function sample_theme_get_path($relative = '') {
        $base = get_template_directory();
        return $base . ($relative ? DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR) : '');
    }

    public static function sample_theme_get_uri($relative = '') {
        return get_template_directory_uri() . '/' . ltrim(str_replace('\\', '/', $relative), '/');
    }
}
