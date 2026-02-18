<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme setup (after_setup_theme).
 */
class Sample_Theme_Setup {

    public static function sample_theme_after_setup() {
        load_theme_textdomain('sample-theme', get_template_directory() . '/languages');
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);
    }
}
