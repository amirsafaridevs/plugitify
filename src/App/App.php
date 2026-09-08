<?php

namespace Plugitify\App;

use Plugitify\Providers\AdminServiceProvider;

final class App
{
	private static ?App $instance = null;

	/**
	 * @var string[] Fully qualified class names of service providers to boot.
	 */
	private array $providers = [
		AdminServiceProvider::class,
	];

	public static function getInstance(): App
	{
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct()
	{
		$this->boot();
	}

	private function __clone()
	{
	}

	public function __wakeup()
	{
		throw new \Exception( 'Cannot unserialize a singleton.' );
	}

	private function boot(): void
	{
		add_action( 'init', [ $this, 'loadTextdomain' ] );

		$this->registerProviders();
	}

	public function loadTextdomain(): void
	{
		load_plugin_textdomain(
			'plugitify',
			false,
			dirname( plugin_basename( PLUGITIFY_FILE ) ) . '/languages'
		);
	}

	private function registerProviders(): void
	{
		foreach ( $this->providers as $providerClass ) {
			$provider = new $providerClass();
			$provider->register();
		}
	}
}
