<?php

namespace Plugitify\Providers;

use Plugitify\Core\ServiceProvider;
use Plugitify\Services\Admin\MuPluginInstallerService;
use Plugitify\Services\Admin\PluginActivationService;
use Plugitify\Services\Admin\PluginBulkActionService;
use Plugitify\Services\Admin\PluginCreationService;
use Plugitify\Services\Admin\PluginDeletionService;
use Plugitify\Services\Admin\PluginsListService;
use Plugitify\Services\Admin\SettingsService;

class AdminServiceProvider extends ServiceProvider
{
	private string $pluginsPageHook = '';

	private string $settingsPageHook = '';

	public function register(): void
	{
		$muPluginInstaller = new MuPluginInstallerService();

		add_action( 'admin_init', [ $muPluginInstaller, 'ensureInstalled' ] );
		add_action( 'admin_notices', [ $muPluginInstaller, 'renderNotice' ] );
		add_action( 'admin_menu', [ $this, 'registerMenus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueMenuIcon' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
		add_action( 'admin_post_plugitify_save_settings', [ new SettingsService(), 'handleSave' ] );
		add_action( 'wp_ajax_plugitify_create_plugin', [ new PluginCreationService(), 'handleAjaxCreate' ] );
		add_action( 'wp_ajax_plugitify_delete_plugin', [ new PluginDeletionService(), 'handleAjaxDelete' ] );
		add_action( 'wp_ajax_plugitify_toggle_plugin', [ new PluginActivationService(), 'handleAjaxToggle' ] );
		add_action( 'wp_ajax_plugitify_bulk_action', [ new PluginBulkActionService(), 'handleAjaxBulk' ] );
	}

	public function enqueueMenuIcon(): void
	{
		wp_enqueue_style(
			'plugitify-menu-icon',
			PLUGITIFY_URL . 'assets/admin/plugitify-menu-icon.css',
			[],
			PLUGITIFY_VERSION
		);
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
			PLUGITIFY_URL . 'assets/admin/favicon.svg?ver=' . PLUGITIFY_VERSION,
			65
		);

		$this->settingsPageHook = (string) add_submenu_page(
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
		$isPluginsPage  = $hook === $this->pluginsPageHook;
		$isSettingsPage = $hook === $this->settingsPageHook;

		if ( ! $isPluginsPage && ! $isSettingsPage ) {
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

		$localize = [
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'chatUrl'     => trailingslashit( home_url( '/plugitify/v1/chat' ) ),
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
		];

		if ( $isSettingsPage ) {
			$providerModels = [];

			foreach ( SettingsService::providers() as $providerId => $provider ) {
				$providerModels[ $providerId ] = $provider['models'];
			}

			$localize['providers']     = $providerModels;
			$localize['currentModel']  = SettingsService::getSettings()['model'];
			$localize['settingsPage']  = true;
		}

		wp_localize_script( 'plugitify-admin', 'plugitifyAdmin', $localize );
	}
}
