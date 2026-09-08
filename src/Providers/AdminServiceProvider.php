<?php

namespace Plugitify\Providers;

use Plugitify\Core\ServiceProvider;
use Plugitify\Services\Admin\PluginActivationService;
use Plugitify\Services\Admin\PluginBulkActionService;
use Plugitify\Services\Admin\PluginCreationService;
use Plugitify\Services\Admin\PluginDeletionService;
use Plugitify\Services\Admin\PluginsListService;
use Plugitify\Services\Admin\SettingsService;

class AdminServiceProvider extends ServiceProvider
{
	private string $pluginsPageHook = '';

	public function register(): void
	{
		add_action( 'admin_menu', [ $this, 'registerMenus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
		add_action( 'wp_ajax_plugitify_create_plugin', [ new PluginCreationService(), 'handleAjaxCreate' ] );
		add_action( 'wp_ajax_plugitify_delete_plugin', [ new PluginDeletionService(), 'handleAjaxDelete' ] );
		add_action( 'wp_ajax_plugitify_toggle_plugin', [ new PluginActivationService(), 'handleAjaxToggle' ] );
		add_action( 'wp_ajax_plugitify_bulk_action', [ new PluginBulkActionService(), 'handleAjaxBulk' ] );
	}

	public function registerMenus(): void
	{
		$capability = 'manage_options';

		$this->pluginsPageHook = (string) add_menu_page(
			__( 'پلاگیتی', 'plugitify' ),
			__( 'پلاگیتی', 'plugitify' ),
			$capability,
			'plugitify',
			[ new PluginsListService(), 'render' ],
			'dashicons-admin-plugins',
			65
		);

		add_submenu_page(
			'plugitify',
			__( 'تنظیمات', 'plugitify' ),
			__( 'تنظیمات', 'plugitify' ),
			$capability,
			'plugitify-settings',
			[ new SettingsService(), 'render' ]
		);
	}

	public function enqueueAssets( string $hook ): void
	{
		if ( $hook !== $this->pluginsPageHook ) {
			return;
		}

		wp_enqueue_style(
			'plugitify-admin',
			PLUGITIFY_URL . 'assets/admin/plugitify-admin.css',
			[],
			PLUGITIFY_VERSION
		);

		wp_enqueue_script(
			'plugitify-admin',
			PLUGITIFY_URL . 'assets/admin/plugitify-admin.js',
			[],
			PLUGITIFY_VERSION,
			true
		);

		wp_localize_script(
			'plugitify-admin',
			'plugitifyAdmin',
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'plugitify_create_plugin' ),
				'deleteNonce' => wp_create_nonce( 'plugitify_delete_plugin' ),
				'toggleNonce' => wp_create_nonce( 'plugitify_toggle_plugin' ),
				'bulkNonce'   => wp_create_nonce( 'plugitify_bulk_action' ),
				'strings'     => [
					'errorName'         => __( 'نام افزونه را وارد کنید.', 'plugitify' ),
					'errorSlug'         => __( 'اسلاگ باید انگلیسی باشد و فقط شامل حروف کوچک، عدد و خط تیره باشد.', 'plugitify' ),
					'errorDescription'  => __( 'توضیحات افزونه را وارد کنید.', 'plugitify' ),
					'errorGeneric'      => __( 'خطایی رخ داد، دوباره تلاش کنید.', 'plugitify' ),
					'confirmDelete'     => __( 'آیا از حذف «%s» مطمئن هستید؟ این کار غیرقابل بازگشت است.', 'plugitify' ),
					'confirmBulkDelete' => __( 'آیا از حذف افزونه‌های انتخاب‌شده مطمئن هستید؟ این کار غیرقابل بازگشت است.', 'plugitify' ),
				],
			]
		);
	}
}
