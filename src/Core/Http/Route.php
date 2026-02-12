<?php

namespace Plugifity\Core\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single route definition (Laravel-style). Method, URI, action, middleware, name.
 */
class Route
{
	private string $method;

	private string $uri;

	/** @var callable|array{0: class-string|object, 1: string} */
	private $action;

	/** @var array<int, string|class-string> */
	private array $middleware = [];

	private ?string $name = null;

	/** @var array<int, string> Parameter names from URI, e.g. ['id'] for /user/{id} */
	private array $parameterNames = [];

	public function __construct( string $method, string $uri, callable|array $action )
	{
		$this->method = strtoupper( $method );
		$this->uri    = '/' . trim( $uri, '/' );
		$this->action = $action;
		$this->parseParameterNames();
	}

	/**
	 * Set route name (for URL generation and lookup).
	 */
	public function name( string $name ): self
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * Set middleware (alias names or class names).
	 *
	 * @param string|array<int, string> $middleware
	 */
	public function middleware( string|array $middleware ): self
	{
		$this->middleware = array_merge( $this->middleware, is_array( $middleware ) ? $middleware : [ $middleware ] );
		return $this;
	}

	public function getMethod(): string
	{
		return $this->method;
	}

	public function getUri(): string
	{
		return $this->uri;
	}

	/**
	 * @return callable|array{0: class-string|object, 1: string}
	 */
	public function getAction()
	{
		return $this->action;
	}

	/**
	 * @return array<int, string|class-string>
	 */
	public function getMiddleware(): array
	{
		return $this->middleware;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	/**
	 * @return array<int, string>
	 */
	public function getParameterNames(): array
	{
		return $this->parameterNames;
	}

	/**
	 * Convert URI to WordPress rewrite regex (no leading slash in result).
	 * E.g. /dashboard -> ^prefix/dashboard/?$, /user/{id} -> ^prefix/user/([^/]+)/?$
	 */
	public function toRewriteRegex( string $prefix ): string
	{
		$placeholder = '__WP_REWRITE_PARAM__';
		$path        = trim( $this->uri, '/' );
		$path        = preg_replace( '#\{[^}]+\}#', $placeholder, $path );
		$path        = preg_quote( $path, '#' );
		$path        = str_replace( $placeholder, '([^/]+)', $path );
		$prefix      = trim( $prefix, '/' );
		return '^' . $prefix . '/' . $path . '/?$';
	}

	/**
	 * Path key for rewrite (same path = same key; used with method to find route).
	 */
	public function getPathKey(): string
	{
		$path = trim( $this->uri, '/' );
		$path = preg_replace( '#\{[^}]+\}#', '', $path );
		$path = trim( preg_replace( '#/+#', '_', $path ), '_' );
		return $path ?: 'index';
	}

	/**
	 * Build redirect query string for WordPress add_rewrite_rule.
	 * Uses path key so same URL can match GET/POST by method in dispatch.
	 */
	public function toRewriteRedirect( string $routeQueryVar, string $paramQueryVarPrefix ): string
	{
		$key = $this->getPathKey();
		$out = 'index.php?' . $routeQueryVar . '=' . $key;
		$i   = 1;
		foreach ( $this->parameterNames as $_ ) {
			$out .= '&' . $paramQueryVarPrefix . $i . '=$matches[' . $i . ']';
			$i++;
		}
		return $out;
	}

	/**
	 * Resolve action to a callable (controller@method or closure).
	 *
	 * @param callable|null $resolver  function (Route $route): callable
	 * @return callable function (Request $request, array $params): mixed
	 */
	public function resolveAction( ?callable $resolver = null ): callable
	{
		$action = $this->action;
		if ( $resolver !== null ) {
			return $resolver( $this );
		}
		if ( is_callable( $action ) ) {
			return function ( Request $request, array $params ) use ( $action ) {
				return $action( $request, ...array_values( $params ) );
			};
		}
		if ( is_array( $action ) && count( $action ) >= 2 ) {
			[ $controller, $method ] = $action;
			return function ( Request $request, array $params ) use ( $controller, $method ) {
				$instance = is_object( $controller ) ? $controller : new $controller();
				return $instance->{$method}( $request, ...array_values( $params ) );
			};
		}
		return function () {
			return null;
		};
	}

	private function parseParameterNames(): void
	{
		if ( preg_match_all( '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $this->uri, $m ) ) {
			$this->parameterNames = $m[1];
		}
	}

	private function sanitizeRouteKey(): string
	{
		$s = strtolower( $this->method . '_' . trim( $this->uri, '/' ) );
		$s = preg_replace( '/[^a-z0-9_]/', '_', $s );
		return $s ?: 'route';
	}
}
