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
		// Plugitify screens render a richer, self-contained banner instead.
		if ( $this->isPlugitifyScreen() ) {
			return;
		}

		$message = get_transient( self::NOTICE_TRANSIENT );

		if ( ! is_string( $message ) || '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Describes the current state of wp-content/mu-plugins/plugitify.php so the
	 * admin views can warn about it and show manual copy instructions.
	 *
	 * @return array{
	 *     ready: bool,
	 *     state: string,
	 *     source: string,
	 *     sourceReadable: bool,
	 *     targetDir: string,
	 *     target: string,
	 *     filename: string
	 * }
	 */
	public function getStatus(): array
	{
		$source    = PLUGITIFY_PATH . self::SOURCE_RELATIVE;
		$targetDir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : '';

		$status = [
			'ready'          => false,
			'state'          => 'missing',
			'source'         => wp_normalize_path( $source ),
			'sourceReadable' => is_readable( $source ),
			'targetDir'      => '' !== $targetDir ? wp_normalize_path( $targetDir ) : '',
			'target'         => '' !== $targetDir ? wp_normalize_path( $targetDir . '/' . self::TARGET_FILENAME ) : '',
			'filename'       => self::TARGET_FILENAME,
		];

		if ( '' === $targetDir ) {
			$status['state'] = 'undefined-dir';

			return $status;
		}

		if ( ! is_file( $targetDir . '/' . self::TARGET_FILENAME ) ) {
			return $status;
		}

		if ( ! $status['sourceReadable'] || ! $this->isUpToDate( $source, $targetDir . '/' . self::TARGET_FILENAME ) ) {
			$status['state'] = 'outdated';

			return $status;
		}

		$status['ready'] = true;
		$status['state'] = 'ready';

		return $status;
	}

	private function isPlugitifyScreen(): bool
	{
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen ) {
			return false;
		}

		return false !== strpos( $screen->id, 'plugitify' );
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
