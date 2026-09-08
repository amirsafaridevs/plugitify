<?php
/**
 * Plugin Name: Plugitify - Agent
 * Description: Must-use loader for Plugitify. Boots the MuPlugin runtime on muplugins_loaded.
 * Version: 1.0.0
 *
 * This file is auto-loaded as a must-use plugin. A copy of it lives in
 * wp-content/mu-plugins/plugitify.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( WP_PLUGIN_DIR . '/plugitify/src/muPlugin/muPlugin.php' ) ) {
	require_once WP_PLUGIN_DIR . '/plugitify/src/muPlugin/muPlugin.php';

	add_action(
		'muplugins_loaded',
		static function (): void {
			\Plugitify\muPlugin\MuPlugin::getInstance();
		}
	);
}
