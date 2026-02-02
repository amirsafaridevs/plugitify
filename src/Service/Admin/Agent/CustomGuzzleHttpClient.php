<?php

namespace Plugifity\Service\Admin\Agent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use NeuronAI\HttpClient\GuzzleHttpClient;

/**
 * Custom Guzzle HTTP Client with SSL verification disabled for development on Windows/WAMP.
 * 
 * IMPORTANT: This is for development only. In production, you should:
 * 1. Use a proper CA certificate bundle (cacert.pem)
 * 2. Configure PHP's curl.cainfo in php.ini
 * 3. Remove this class and use the default GuzzleHttpClient
 */
class CustomGuzzleHttpClient extends GuzzleHttpClient
{
	/**
	 * @param array<string,mixed> $customHeaders
	 */
	public function __construct(
		array $customHeaders = [],
		float $timeout = 30.0,
		float $connectTimeout = 10.0,
		?HandlerStack $handler = null,
		private readonly bool $verifySSL = false
	) {
		parent::__construct( $customHeaders, $timeout, $connectTimeout, $handler );
	}

	/**
	 * Override createClient to add SSL verification control.
	 */
	protected function createClient(): Client
	{
		$config = [
			'verify' => $this->verifySSL,
		];

		// Get base URI if set (via reflection since baseUri is protected)
		$reflection = new \ReflectionClass( parent::class );
		$baseUriProperty = $reflection->getProperty( 'baseUri' );
		$baseUriProperty->setAccessible( true );
		$baseUri = $baseUriProperty->getValue( $this );

		if ( $baseUri !== '' ) {
			$config['base_uri'] = rtrim( $baseUri, '/' ) . '/';
		}

		// Get handler if set (via reflection)
		$handlerProperty = $reflection->getProperty( 'handler' );
		$handlerProperty->setAccessible( true );
		$handler = $handlerProperty->getValue( $this );

		if ( $handler instanceof HandlerStack ) {
			$config['handler'] = $handler;
		}

		return new Client( $config );
	}

	public function withSSLVerify( bool $verify ): self
	{
		return new self(
			$this->getCustomHeaders(),
			$this->getTimeout(),
			$this->getConnectTimeout(),
			$this->getHandler(),
			$verify
		);
	}

	private function getCustomHeaders(): array
	{
		$reflection = new \ReflectionClass( parent::class );
		$property = $reflection->getProperty( 'customHeaders' );
		$property->setAccessible( true );
		return $property->getValue( $this );
	}

	private function getTimeout(): float
	{
		$reflection = new \ReflectionClass( parent::class );
		$property = $reflection->getProperty( 'timeout' );
		$property->setAccessible( true );
		return $property->getValue( $this );
	}

	private function getConnectTimeout(): float
	{
		$reflection = new \ReflectionClass( parent::class );
		$property = $reflection->getProperty( 'connectTimeout' );
		$property->setAccessible( true );
		return $property->getValue( $this );
	}

	private function getHandler(): ?HandlerStack
	{
		$reflection = new \ReflectionClass( parent::class );
		$property = $reflection->getProperty( 'handler' );
		$property->setAccessible( true );
		return $property->getValue( $this );
	}
}
