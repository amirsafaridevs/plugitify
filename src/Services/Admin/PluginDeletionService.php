<?php

namespace Plugitify\Services\Admin;

class PluginDeletionService
{
	private const OPTION_KEY = 'plugitify_plugins';

	public function handleAjaxDelete(): void
	{
		check_ajax_referer( 'plugitify_delete_plugin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'شما اجازه‌ی انجام این کار را ندارید.', 'plugitify' ) ],
				403
			);
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';

		if ( ! $this->isValidSlug( $slug ) ) {
			wp_send_json_error( [ 'message' => __( 'اسلاگ نامعتبر است.', 'plugitify' ) ] );
		}

		if ( ! $this->deleteBySlug( $slug ) ) {
			wp_send_json_error( [ 'message' => __( 'حذف پوشه‌ی افزونه با خطا مواجه شد.', 'plugitify' ) ] );
		}

		wp_send_json_success( [ 'message' => __( 'افزونه حذف شد.', 'plugitify' ) ] );
	}

	public function deleteBySlug( string $slug ): bool
	{
		if ( ! $this->isValidSlug( $slug ) ) {
			return false;
		}

		$pluginDir = WP_PLUGIN_DIR . '/' . $slug;

		if ( is_dir( $pluginDir ) && ! $this->deleteDirectory( $pluginDir ) ) {
			return false;
		}

		$this->removeFromOption( $slug );

		return true;
	}

	private function isValidSlug( string $slug ): bool
	{
		return '' !== $slug && 1 === preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug );
	}

	private function deleteDirectory( string $dir ): bool
	{
		$items = scandir( $dir );

		if ( false === $items ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . '/' . $item;

			if ( is_dir( $path ) ) {
				$this->deleteDirectory( $path );
			} else {
				@unlink( $path );
			}
		}

		return @rmdir( $dir );
	}

	private function removeFromOption( string $slug ): void
	{
		$stored  = get_option( self::OPTION_KEY, '[]' );
		$plugins = json_decode( (string) $stored, true );

		if ( ! is_array( $plugins ) ) {
			$plugins = [];
		}

		$plugins = array_values(
			array_filter(
				$plugins,
				static function ( $plugin ) use ( $slug ) {
					return ! isset( $plugin['slug'] ) || $plugin['slug'] !== $slug;
				}
			)
		);

		update_option( self::OPTION_KEY, wp_json_encode( $plugins ) );
	}
}
