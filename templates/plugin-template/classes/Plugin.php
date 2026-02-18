<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core plugin class.
 */
class Sample_Plugin_Core {

    public static function sample_plugin_init() {
        add_action('init', [__CLASS__, 'sample_plugin_register_hooks']);
    }

    public static function sample_plugin_register_hooks() {
        if (is_admin()) {
            Sample_Plugin_Admin::sample_plugin_admin_init();
        }
    }

    public static function sample_plugin_get_version() {
        return SAMPLE_PLUGIN_VERSION;
    }

    public static function sample_plugin_get_path($relative = '') {
        return SAMPLE_PLUGIN_PATH . ($relative ? DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR) : '');
    }

    public static function sample_plugin_get_url($relative = '') {
        return SAMPLE_PLUGIN_URL . ltrim(str_replace('\\', '/', $relative), '/');
    }
}
