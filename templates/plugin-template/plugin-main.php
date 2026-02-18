<?php

/**
 * Plugin Name: Sample Plugin
 * Description: Sample Plugin – WordPress plugin.
 * Version: 1.0.0
 * Author:
 * Author URI:
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sample-plugin
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SAMPLE_PLUGIN_FILE', __FILE__);
define('SAMPLE_PLUGIN_PATH', __DIR__);
define('SAMPLE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAMPLE_PLUGIN_VERSION', '1.0.0');

// Load classes
$sample_plugin_classes_dir = __DIR__ . DIRECTORY_SEPARATOR . 'classes';
if (is_dir($sample_plugin_classes_dir)) {
    $sample_plugin_files = glob($sample_plugin_classes_dir . DIRECTORY_SEPARATOR . '*.php');
    foreach (is_array($sample_plugin_files) ? $sample_plugin_files : [] as $sample_plugin_file) {
        require_once $sample_plugin_file;
    }
}

add_action('plugins_loaded', 'sample_plugin_boot');
function sample_plugin_boot() {
    Sample_Plugin_Core::sample_plugin_init();
}

add_action('wp_enqueue_scripts', 'sample_plugin_enqueue_assets');
function sample_plugin_enqueue_assets() {
    wp_enqueue_style(
        'sample-plugin-main',
        SAMPLE_PLUGIN_URL . 'assets/css/style.css',
        [],
        SAMPLE_PLUGIN_VERSION
    );
    wp_enqueue_script(
        'sample-plugin-main',
        SAMPLE_PLUGIN_URL . 'assets/js/script.js',
        [],
        SAMPLE_PLUGIN_VERSION,
        true
    );
}
