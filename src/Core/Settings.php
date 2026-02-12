<?php

namespace Plugifity\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class – get/set options with plugin prefix (static API).
 * Prefix is read from Application (app->prefix). Throws if prefix is not set.
 */
class Settings
{
	/**
	 * Cached prefix from Application.
	 *
	 * @var string|null
	 */
	private static ?string $prefix = null;

	/**
	 * Get prefix from Application (cached). Throws if not set.
	 *
	 * @return string
	 * @throws \RuntimeException When prefix is not defined on Application.
	 */
	private static function getPrefix(): string
	{
		if ( self::$prefix !== null ) {
			return self::$prefix;
		}
		try {
			$application = Application::get();
			$prefix = $application->prefix ?? null;
			if ( $prefix === null || $prefix === '' ) {
				throw new \RuntimeException( __( 'Prefix is not defined.', 'plugitify' ) );
			}
			self::$prefix = $prefix;
			return self::$prefix;
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( __( 'Prefix is not defined.', 'plugitify' ), 0, $e );
		}
	}

	/**
	 * Build prefixed option key.
	 *
	 * @param string $key Raw key (e.g. "api_key").
	 * @return string Prefixed key (e.g. "plugifity_api_key").
	 */
	private static function optionKey( string $key ): string
	{
		return self::getPrefix() . '_' . ltrim( $key, '_' );
	}

	/**
	 * Get option(s) by key. Single key (string) or multiple keys (array).
	 *
	 * @param string|array $key    Option key without prefix, or array of keys (list or key => default).
	 * @param mixed        $default Default when key is string and option does not exist. Ignored when $key is array.
	 * @return mixed Single value when $key is string; array<string, mixed> when $key is array.
	 */
	public static function get( string|array $key, $default = null )
	{
		if ( is_array( $key ) ) {
			$withDefaults = [];
			foreach ( $key as $k => $v ) {
				if ( is_int( $k ) ) {
					$withDefaults[ $v ] = null;
				} else {
					$withDefaults[ $k ] = $v;
				}
			}
			$result = [];
			foreach ( $withDefaults as $k => $def ) {
				$result[ $k ] = get_option( self::optionKey( $k ), $def );
			}
			return $result;
		}
		return get_option( self::optionKey( $key ), $default );
	}

	/**
	 * Create or update option(s). Single key + value, or keys with corresponding values.
	 *
	 * @param string|array $key   Option key(s). String = one key; array = list of keys or key => value map.
	 * @param string|array|null $value When $key is string: value to store (string or array). When $key is array and $value is array: values by index (متناظر با keyها).
	 * @return bool True when single set; always true when array.
	 */
	public static function set( string|array $key, string|array|null $value = null ): bool
	{
		if ( is_array( $key ) ) {
			if ( is_array( $value ) ) {
				// آرایه مقادیر متناظر با کلیدها (هم‌ترتیب)
				$option_keys = array_is_list( $key ) ? $key : array_keys( $key );
				$values = array_values( $value );
				foreach ( $option_keys as $i => $k ) {
					update_option( self::optionKey( $k ), $values[ $i ] ?? null );
				}
			} else {
				// $key به صورت key => value
				foreach ( $key as $k => $v ) {
					update_option( self::optionKey( $k ), $v );
				}
			}
			return true;
		}
		return update_option( self::optionKey( $key ), $value );
	}
}
