<?php

if (!defined('ABSPATH')) {
    exit;
}

define('SAMPLE_THEME_VERSION', '1.0.0');

$sample_theme_classes_dir = get_template_directory() . DIRECTORY_SEPARATOR . 'classes';
if (is_dir($sample_theme_classes_dir)) {
    $sample_theme_files = glob($sample_theme_classes_dir . DIRECTORY_SEPARATOR . '*.php');
    foreach (is_array($sample_theme_files) ? $sample_theme_files : [] as $sample_theme_file) {
        require_once $sample_theme_file;
    }
}

add_action('after_setup_theme', 'sample_theme_setup');
function sample_theme_setup() {
    Sample_Theme_Setup::sample_theme_after_setup();
}

add_action('wp_enqueue_scripts', 'sample_theme_enqueue_assets');
function sample_theme_enqueue_assets() {
    wp_enqueue_style(
        'sample-theme-style',
        get_stylesheet_uri(),
        [],
        SAMPLE_THEME_VERSION
    );
    wp_enqueue_style(
        'sample-theme-main',
        get_template_directory_uri() . '/assets/css/style.css',
        [],
        SAMPLE_THEME_VERSION
    );
    wp_enqueue_script(
        'sample-theme-main',
        get_template_directory_uri() . '/assets/js/script.js',
        [],
        SAMPLE_THEME_VERSION,
        true
    );
}

Sample_Theme_Core::sample_theme_init();
