<?php

/**
 * Plugin Name: Plugifity
 * Description: Plugifity is a AI powered plugin that allows administrators to create and manage their own plugins.
 * Version: 1.0.0
 * Author: Amir Safari
 * Author URI: https://wpagentify.com
 * Text Domain: plugifity
 * Domain Path: /languages
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

\Plugifity\App\App::get();