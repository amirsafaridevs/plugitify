<?php

namespace Plugitify\Services\Admin;

class PluginActivationService
{
	public function handleAjaxToggle(): void
	{
		check_ajax_referer( 'plugitify_toggle_plugin', 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'شما اجازه‌ی انجام این کار را ندارید.', 'plugitify' ) ],
				403
			);
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';

		if ( ! $this->isValidSlug( $slug ) ) {
			wp_send_json_error( [ 'message' => __( 'اسلاگ نامعتبر است.', 'plugitify' ) ] );
		}

		if ( ! is_dir( WP_PLUGIN_DIR . '/' . $slug ) ) {
			wp_send_json_error( [ 'message' => __( 'پوشه‌ی افزونه پیدا نشد.', 'plugitify' ) ] );
		}

		$this->ensurePluginApiLoaded();

		$pluginFile = $slug . '/' . $slug . '.php';

		if ( ! is_file( WP_PLUGIN_DIR . '/' . $pluginFile ) ) {
			wp_send_json_error( [ 'message' => __( 'فایل اصلی افزونه پیدا نشد.', 'plugitify' ) ] );
		}

		if ( is_plugin_active( $pluginFile ) ) {
			$this->deactivateBySlug( $slug );

			wp_send_json_success( [ 'active' => false ] );
		}

		$result = $this->activateBySlug( $slug );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'active' => true ] );
	}

	/**
	 * @return true|\WP_Error
	 */
	public function activateBySlug( string $slug )
	{
		$this->ensurePluginApiLoaded();

		$pluginFile = $slug . '/' . $slug . '.php';

		if ( ! is_file( WP_PLUGIN_DIR . '/' . $pluginFile ) || is_plugin_active( $pluginFile ) ) {
			return true;
		}

		return activate_plugin( $pluginFile );
	}

	public function deactivateBySlug( string $slug ): void
	{
		$this->ensurePluginApiLoaded();

		$pluginFile = $slug . '/' . $slug . '.php';

		if ( is_plugin_active( $pluginFile ) ) {
			deactivate_plugins( $pluginFile );
		}
	}

	private function ensurePluginApiLoaded(): void
	{
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	private function isValidSlug( string $slug ): bool
	{
		return '' !== $slug && 1 === preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug );
	}
}
