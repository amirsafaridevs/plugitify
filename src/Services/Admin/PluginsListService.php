<?php

namespace Plugitify\Services\Admin;

class PluginsListService
{
	private const OPTION_KEY = 'plugitify_plugins';

	public function render(): void
	{
		$plugins        = $this->getPlugitifyPlugins();
		$muPluginStatus = ( new MuPluginInstallerService() )->getStatus();

		include PLUGITIFY_PATH . 'src/Views/Admin/plugins-list.php';
	}

	private function getPlugitifyPlugins(): array
	{
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$entries = $this->getStoredEntries();
		$valid   = [];
		$plugins = [];
		$changed = false;

		foreach ( $entries as $entry ) {
			if ( empty( $entry['slug'] ) || ! is_dir( WP_PLUGIN_DIR . '/' . $entry['slug'] ) ) {
				$changed = true;
				continue;
			}

			$valid[] = $entry;

			$slug       = $entry['slug'];
			$pluginFile = $slug . '/' . $slug . '.php';
			$fullPath   = WP_PLUGIN_DIR . '/' . $pluginFile;

			$name        = ! empty( $entry['name'] ) ? $entry['name'] : $slug;
			$description = ! empty( $entry['description'] ) ? $entry['description'] : '';
			$version     = '';
			$isActive    = false;

			if ( is_file( $fullPath ) ) {
				$data        = get_plugin_data( $fullPath, false, false );
				$name        = ! empty( $data['Name'] ) ? $data['Name'] : $name;
				$version     = ! empty( $data['Version'] ) ? $data['Version'] : $version;
				$description = ! empty( $data['Description'] ) ? $data['Description'] : $description;
				$isActive    = is_plugin_active( $pluginFile );
			}

			$plugins[ $pluginFile ] = [
				'Slug'        => $slug,
				'Name'        => $name,
				'Version'     => $version,
				'Description' => $description,
				'IsActive'    => $isActive,
			];
		}

		if ( $changed ) {
			update_option( self::OPTION_KEY, wp_json_encode( $valid ) );
		}

		return $plugins;
	}

	private function getStoredEntries(): array
	{
		$stored  = get_option( self::OPTION_KEY, '[]' );
		$entries = json_decode( (string) $stored, true );

		return is_array( $entries ) ? $entries : [];
	}
}
