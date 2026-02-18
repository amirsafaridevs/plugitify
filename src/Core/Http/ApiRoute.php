<?php

namespace Plugifity\Core\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single API route for WordPress REST API (Laravel-style definition).
 * Stored by ApiRouter and registered via register_rest_route().
 */
class ApiRoute
{
	/** @var array<int, string> */
	private array $methods;

	private string $uri;

	/** @var callable|array{0: class-string|object, 1: string}|string */
	private $action;

	/** @var callable(WP_REST_Request): bool|WP_Error|null */
	private $permissionCallback = null;

	private ?string $name = null;

	/** Tool slug for settings-based enable/disable (e.g. 'query', 'file', 'general'). */
	private ?string $toolSlug = null;

	/** Endpoint slug within the tool (e.g. 'read', 'execute'). Used for per-endpoint enable/disable. */
	private ?string $endpointSlug = null;

	/** @var array<int, string> */
	private array $parameterNames = [];

	/**
	 * @param string|array<int, string> $methods  GET, POST, PUT, PATCH, DELETE
	 * @param string $uri  Laravel-style: /users, /users/{id}
	 * @param callable|array{0: class-string|object, 1: string}|string $action  Closure, [Controller::class, 'method'], or "Controller@method"
	 */
	public function __construct( string|array $methods, string $uri, callable|array|string $action )
	{
		$this->methods = is_array( $methods ) ? $methods : [ $methods ];
		$this->methods = array_map( 'strtoupper', $this->methods );
		$this->uri     = '/' . trim( $uri, '/' );
		$this->action  = $action;
		$this->parseParameterNames();
	}

	public function name( string $name ): self
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * Mark route as belonging to a tool and optional endpoint (for settings: enable/disable per endpoint).
	 *
	 * @param string      $slug   Tool slug (e.g. 'query', 'file', 'general').
	 * @param string|null $endpoint Endpoint slug (e.g. 'read', 'execute'). Omit for routes not in settings (e.g. ping).
	 */
	public function tool( string $slug, ?string $endpoint = null ): self
	{
		$this->toolSlug     = $slug;
		$this->endpointSlug = $endpoint;
		return $this;
	}

	public function getTool(): ?string
	{
		return $this->toolSlug;
	}

	public function getEndpoint(): ?string
	{
		return $this->endpointSlug;
	}

	/**
	 * Permission callback for REST route (WordPress: permission_callback).
	 *
	 * @param callable(WP_REST_Request): bool|WP_Error $callback
	 */
	public function permission( callable $callback ): self
	{
		$this->permissionCallback = $callback;
		return $this;
	}

	/** @return array<int, string> */
	public function getMethods(): array
	{
		return $this->methods;
	}

	public function getUri(): string
	{
		return $this->uri;
	}

	/** @return callable|array{0: class-string|object, 1: string}|string */
	public function getAction()
	{
		return $this->action;
	}

	/** @return callable|null */
	public function getPermissionCallback(): ?callable
	{
		return $this->permissionCallback;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	/** @return array<int, string> */
	public function getParameterNames(): array
	{
		return $this->parameterNames;
	}

	/**
	 * Convert Laravel URI to WordPress REST route string (no leading slash).
	 * {id} -> (?P<id>[^/]+)
	 */
	public function toWpRestRoute(): string
	{
		$path = trim( $this->uri, '/' );
		if ( $path === '' ) {
			return '';
		}
		$path = preg_replace( '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path );
		return $path;
	}

	/**
	 * Resolve action to callable. Returns function( Request $request, ...$params ) that returns response array or WP_REST_Response.
	 *
	 * @param callable|null $resolver  function( ApiRoute $route ): callable
	 */
	public function resolveAction( ?callable $resolver = null ): callable
	{
		$action = $this->action;
		if ( $resolver !== null ) {
			return $resolver( $this );
		}
		if ( is_callable( $action ) ) {
			return $action;
		}
		if ( is_array( $action ) && count( $action ) >= 2 ) {
			[ $controller, $method ] = $action;
			return function ( Request $request, array $params ) use ( $controller, $method ) {
				$instance = is_object( $controller ) ? $controller : new $controller();
				return $instance->{$method}( $request, ...array_values( $params ) );
			};
		}
		// "Controller@method" string
		if ( is_string( $action ) && str_contains( $action, '@' ) ) {
			[ $class, $method ] = explode( '@', $action, 2 );
			return function ( Request $request, array $params ) use ( $class, $method ) {
				$instance = new $class();
				return $instance->{$method}( $request, ...array_values( $params ) );
			};
		}
		return function () {
			return [];
		};
	}

	private function parseParameterNames(): void
	{
		if ( preg_match_all( '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $this->uri, $m ) ) {
			$this->parameterNames = $m[1];
		}
	}
}
