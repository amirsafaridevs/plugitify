<?php

/**
 * Plugin Name: Plugifity
 * Description: Plugifity is a AI powered plugin that allows administrators to create and manage their own plugins.
 * Version: 1.0.0
 * Author: Amir Safari
 * Author URI: https://wpagentify.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: plugitify
 * Domain Path: /languages
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PLUGITIFY_PLUGIN_FILE' ) ) {
	define( 'PLUGITIFY_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/vendor/autoload.php';

\Plugifity\App\App::get();