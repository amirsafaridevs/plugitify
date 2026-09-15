<?php

namespace Plugitify\Services\Admin;

class PluginCreationService
{
	private const OPTION_KEY = 'plugitify_plugins';

	public function handleAjaxCreate(): void
	{
		check_ajax_referer( 'plugitify_create_plugin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'شما اجازه‌ی انجام این کار را ندارید.', 'plugitify' ) ],
				403
			);
		}

		$muPluginStatus = ( new MuPluginInstallerService() )->getStatus();

		if ( 'missing' === $muPluginStatus['state'] || 'undefined-dir' === $muPluginStatus['state'] ) {
			wp_send_json_error(
				[
					'message' => __( 'فایل mu-plugin پلاگیتی در پوشه‌ی mu-plugins وردپرس قرار نگرفته است. تا کپی شدن آن، ساخت افزونه‌ی جدید ممکن نیست.', 'plugitify' ),
				],
				409
			);
		}

		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$slug        = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';

		if ( '' === $name ) {
			wp_send_json_error( [ 'message' => __( 'نام افزونه را وارد کنید.', 'plugitify' ) ] );
		}

		if ( ! $this->isValidSlug( $slug ) ) {
			wp_send_json_error(
				[ 'message' => __( 'اسلاگ باید انگلیسی باشد و فقط شامل حروف کوچک، عدد و خط تیره باشد.', 'plugitify' ) ]
			);
		}

		if ( '' === $description ) {
			wp_send_json_error( [ 'message' => __( 'توضیحات افزونه را وارد کنید.', 'plugitify' ) ] );
		}

		if ( $this->slugExists( $slug ) ) {
			wp_send_json_error( [ 'message' => __( 'این اسلاگ قبلاً استفاده شده است.', 'plugitify' ) ] );
		}

		$pluginDir = WP_PLUGIN_DIR . '/' . $slug;

		if ( ! wp_mkdir_p( $pluginDir ) ) {
			wp_send_json_error( [ 'message' => __( 'ایجاد پوشه‌ی افزونه با خطا مواجه شد.', 'plugitify' ) ] );
		}

		$mainFile = $pluginDir . '/' . $slug . '.php';

		if ( false === file_put_contents( $mainFile, $this->buildMainFileContents( $name, $description ) ) ) {
			@rmdir( $pluginDir );
			wp_send_json_error( [ 'message' => __( 'ایجاد فایل اصلی افزونه با خطا مواجه شد.', 'plugitify' ) ] );
		}

		$this->storePlugin( $slug, $name, $description );

		wp_send_json_success(
			[
				'message' => __( 'افزونه با موفقیت ایجاد شد.', 'plugitify' ),
				'slug'    => $slug,
				'name'    => $name,
			]
		);
	}

	private function buildMainFileContents( string $name, string $description ): string
	{
		$name        = str_replace( [ '*/', '/*' ], '', $name );
		$description = str_replace( [ '*/', '/*' ], '', $description );

		return "<?php\n"
			. "/**\n"
			. " * Plugin Name:       {$name}\n"
			. " * Description:       {$description}\n"
			. " * Version:           1.0.0\n"
			. " * Author:            plugitify\n"
			. " * Text Domain:       plugitify\n"
			. " */\n";
	}

	private function isValidSlug( string $slug ): bool
	{
		if ( '' === $slug ) {
			return false;
		}

		return 1 === preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug );
	}

	private function slugExists( string $slug ): bool
	{
		if ( is_dir( WP_PLUGIN_DIR . '/' . $slug ) ) {
			return true;
		}

		foreach ( $this->getPlugins() as $plugin ) {
			if ( isset( $plugin['slug'] ) && $plugin['slug'] === $slug ) {
				return true;
			}
		}

		return false;
	}

	private function getPlugins(): array
	{
		$stored  = get_option( self::OPTION_KEY, '[]' );
		$plugins = json_decode( (string) $stored, true );

		return is_array( $plugins ) ? $plugins : [];
	}

	private function storePlugin( string $slug, string $name, string $description ): void
	{
		$plugins   = $this->getPlugins();
		$plugins[] = [
			'slug'        => $slug,
			'name'        => $name,
			'description' => $description,
			'created_at'  => gmdate( 'c' ),
		];

		update_option( self::OPTION_KEY, wp_json_encode( $plugins ) );
	}
}
