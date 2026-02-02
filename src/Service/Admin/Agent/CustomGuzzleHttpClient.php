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
	private bool $verifySSL = false;

	/**
	 * @param array<string,mixed> $customHeaders
	 */
	public function __construct(
		array $customHeaders = [],
		float $timeout = 30.0,
		float $connectTimeout = 10.0,
		?HandlerStack $handler = null,
		bool $verifySSL = false
	) {
		parent::__construct( $customHeaders, $timeout, $connectTimeout, $handler );
		$this->verifySSL = $verifySSL;
	}

	/**
	 * Override createClient to add SSL verification control.
	 */
	protected function createClient(): Client
	{
		$config = [
			'verify' => $this->verifySSL,
		];

		// Get parent class properties via reflection
		$parentClass = get_parent_class( $this );
		$reflection = new \ReflectionClass( $parentClass );

		// Get base URI
		$baseUriProperty = $reflection->getProperty( 'baseUri' );
		$baseUriProperty->setAccessible( true );
		$baseUri = $baseUriProperty->getValue( $this );

		if ( $baseUri !== '' ) {
			$config['base_uri'] = rtrim( $baseUri, '/' ) . '/';
		}

		// Get handler
		$handlerProperty = $reflection->getProperty( 'handler' );
		$handlerProperty->setAccessible( true );
		$handler = $handlerProperty->getValue( $this );

		if ( $handler instanceof HandlerStack ) {
			$config['handler'] = $handler;
		}

		return new Client( $config );
	}

	/**
	 * Override withBaseUri to return CustomGuzzleHttpClient instance.
	 */
	public function withBaseUri( string $baseUri ): self
	{
		$new = new self(
			$this->getCustomHeaders(),
			$this->getTimeout(),
			$this->getConnectTimeout(),
			$this->getHandler(),
			$this->verifySSL
		);
		
		// Set baseUri via reflection
		$this->setBaseUriOnInstance( $new, $baseUri );
		
		return $new;
	}

	/**
	 * Override withHeaders to return CustomGuzzleHttpClient instance.
	 */
	public function withHeaders( array $headers ): self
	{
		$new = new self(
			[ ...$this->getCustomHeaders(), ...$headers ],
			$this->getTimeout(),
			$this->getConnectTimeout(),
			$this->getHandler(),
			$this->verifySSL
		);
		
		// Preserve baseUri
		$baseUri = $this->getBaseUri();
		$this->setBaseUriOnInstance( $new, $baseUri );
		
		return $new;
	}

	/**
	 * Override withTimeout to return CustomGuzzleHttpClient instance.
	 */
	public function withTimeout( float $timeout ): self
	{
		$new = new self(
			$this->getCustomHeaders(),
			$timeout,
			$this->getConnectTimeout(),
			$this->getHandler(),
			$this->verifySSL
		);
		
		// Preserve baseUri
		$baseUri = $this->getBaseUri();
		$this->setBaseUriOnInstance( $new, $baseUri );
		
		return $new;
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

	private function getParentReflection(): \ReflectionClass
	{
		static $reflection = null;
		if ( $reflection === null ) {
			$parentClass = get_parent_class( $this );
			$reflection = new \ReflectionClass( $parentClass );
		}
		return $reflection;
	}

	private function getCustomHeaders(): array
	{
		$property = $this->getParentReflection()->getProperty( 'customHeaders' );
		$property->setAccessible( true );
		return $property->getValue( $this );
	}

	private function getTimeout(): float
	{
		$property = $this->getParentReflection()->getProperty( 'timeout' );
		$property->setAccessible( true );
		return $property->getValue( $this );
	}

	private function getConnectTimeout(): float
	{
		$property = $this->getParentReflection()->getProperty( 'connectTimeout' );
		$property->setAccessible( true );
		return $property->getValue( $this );
	}

	private function getHandler(): ?HandlerStack
	{
		$property = $this->getParentReflection()->getProperty( 'handler' );
		$property->setAccessible( true );
		return $property->getValue( $this );
	}

	private function getBaseUri(): string
	{
		$property = $this->getParentReflection()->getProperty( 'baseUri' );
		$property->setAccessible( true );
		return $property->getValue( $this );
	}

	private function setBaseUriOnInstance( self $instance, string $baseUri ): void
	{
		$property = $this->getParentReflection()->getProperty( 'baseUri' );
		$property->setAccessible( true );
		$property->setValue( $instance, $baseUri );
	}
}
