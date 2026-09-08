<?php

namespace Plugitify\Services\Admin;

class PluginBulkActionService
{
	public function handleAjaxBulk(): void
	{
		check_ajax_referer( 'plugitify_bulk_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'شما اجازه‌ی انجام این کار را ندارید.', 'plugitify' ) ],
				403
			);
		}

		$bulkAction = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$rawSlugs   = isset( $_POST['slugs'] ) && is_array( $_POST['slugs'] ) ? wp_unslash( $_POST['slugs'] ) : [];
		$slugs      = array_values( array_filter( array_map( 'sanitize_text_field', $rawSlugs ), [ $this, 'isValidSlug' ] ) );

		if ( empty( $slugs ) ) {
			wp_send_json_error( [ 'message' => __( 'هیچ افزونه‌ای انتخاب نشده است.', 'plugitify' ) ] );
		}

		switch ( $bulkAction ) {
			case 'activate':
				$activation = new PluginActivationService();

				foreach ( $slugs as $slug ) {
					$activation->activateBySlug( $slug );
				}
				break;

			case 'deactivate':
				$activation = new PluginActivationService();

				foreach ( $slugs as $slug ) {
					$activation->deactivateBySlug( $slug );
				}
				break;

			case 'delete':
				$deletion = new PluginDeletionService();

				foreach ( $slugs as $slug ) {
					$deletion->deleteBySlug( $slug );
				}
				break;

			default:
				wp_send_json_error( [ 'message' => __( 'عملیات نامعتبر است.', 'plugitify' ) ] );
		}

		wp_send_json_success();
	}

	private function isValidSlug( string $slug ): bool
	{
		return '' !== $slug && 1 === preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug );
	}
}
