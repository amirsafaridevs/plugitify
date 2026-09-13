<?php
/**
 * Plugin Name: Plugitify - Agent
 * Description: Must-use loader for Plugitify. Boots the MuPlugin runtime on muplugins_loaded.
 * Version: 1.0.0
 *
 * Canonical source: wp-content/plugins/plugitify/src/muPlugin/main/plugitify.php
 * It is copied to wp-content/mu-plugins/plugitify.php on every admin request
 * by Plugitify\Services\Admin\MuPluginInstallerService. Do not edit the copy.
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
