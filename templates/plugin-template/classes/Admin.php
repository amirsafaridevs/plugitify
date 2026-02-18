<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin-related functionality.
 */
class Sample_Plugin_Admin {

    public static function sample_plugin_admin_init() {
        add_action('admin_menu', [__CLASS__, 'sample_plugin_add_menu']);
    }

    public static function sample_plugin_add_menu() {
        add_options_page(
            __('Sample Plugin Settings', 'sample-plugin'),
            __('Sample Plugin', 'sample-plugin'),
            'manage_options',
            'sample-plugin-settings',
            [__CLASS__, 'sample_plugin_render_settings_page']
        );
    }

    public static function sample_plugin_render_settings_page() {
        echo '<div class="wrap"><h1>' . esc_html__('Sample Plugin Settings', 'sample-plugin') . '</h1></div>';
    }
}
