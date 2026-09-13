<?php

namespace Plugitify\Services\Admin;

/**
 * Keeps wp-content/mu-plugins/plugitify.php in sync with the copy shipped
 * inside the plugin at src/muPlugin/main/plugitify.php.
 */
class MuPluginInstallerService
{
	private const SOURCE_RELATIVE = 'src/muPlugin/main/plugitify.php';

	private const TARGET_FILENAME = 'plugitify.php';

	private const NOTICE_TRANSIENT = 'plugitify_mu_plugin_error';

	/**
	 * Copies the loader into mu-plugins when it is missing or outdated.
	 */
	public function ensureInstalled(): bool
	{
		$source = PLUGITIFY_PATH . self::SOURCE_RELATIVE;

		if ( ! is_readable( $source ) ) {
			$this->reportError(
				__( 'فایل بارگذار افزونه‌ی الزامی در پوشه‌ی پلاگیتی پیدا نشد.', 'plugitify' )
			);

			return false;
		}

		if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
			return false;
		}

		$targetDir = WPMU_PLUGIN_DIR;
		$target    = $targetDir . '/' . self::TARGET_FILENAME;

		if ( ! is_dir( $targetDir ) && ! wp_mkdir_p( $targetDir ) ) {
			$this->reportError(
				sprintf(
					/* translators: %s: mu-plugins directory path. */
					__( 'ساخت پوشه‌ی %s ممکن نشد. لطفاً دسترسی نوشتن را بررسی کنید.', 'plugitify' ),
					$targetDir
				)
			);

			return false;
		}

		if ( $this->isUpToDate( $source, $target ) ) {
			delete_transient( self::NOTICE_TRANSIENT );

			return true;
		}

		if ( ! @copy( $source, $target ) ) {
			$this->reportError(
				sprintf(
					/* translators: %s: mu-plugins file path. */
					__( 'کپی فایل بارگذار در %s ممکن نشد. لطفاً دسترسی نوشتن را بررسی کنید.', 'plugitify' ),
					$target
				)
			);

			return false;
		}

		delete_transient( self::NOTICE_TRANSIENT );

		return true;
	}

	public function renderNotice(): void
	{
		$message = get_transient( self::NOTICE_TRANSIENT );

		if ( ! is_string( $message ) || '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	private function isUpToDate( string $source, string $target ): bool
	{
		if ( ! is_file( $target ) ) {
			return false;
		}

		if ( filesize( $source ) !== filesize( $target ) ) {
			return false;
		}

		return md5_file( $source ) === md5_file( $target );
	}

	private function reportError( string $message ): void
	{
		set_transient( self::NOTICE_TRANSIENT, $message, HOUR_IN_SECONDS );
	}
}
