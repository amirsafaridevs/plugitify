<?php

namespace Plugifity\Core\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Laravel-style API Router using WordPress REST API (register_rest_route).
 * For API only. Define routes with class/method or closure, then boot on rest_api_init.
 *
 * Usage:
 *   ApiRouter::get( '/users', [ UserController::class, 'index' ] )->name( 'api.users.index' );
 *   ApiRouter::post( '/users', [ UserController::class, 'store' ] );
 *   ApiRouter::get( '/users/{id}', [ UserController::class, 'show' ] );
 *   ApiRouter::boot();  // call once (e.g. from service provider)
 */
class ApiRouter
{
	/** @var array<int, ApiRoute> */
	private array $routes = [];

	private string $namespace = 'plugitify/v1';

	private string $routePrefix = '';

	/** @var callable|null function( ApiRoute ): callable */
	private $actionResolver = null;

	private static ?self $instance = null;

	public static function getInstance(): self
	{
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * GET route.
	 *
	 * @param string $uri  e.g. /users, /users/{id}
	 * @param callable|array|string $action  [Controller::class, 'method'], "Controller@method", or closure
	 */
	public static function get( string $uri, callable|array|string $action ): ApiRoute
	{
		return self::getInstance()->addRoute( 'GET', $uri, $action );
	}

	/** POST route. */
	public static function post( string $uri, callable|array|string $action ): ApiRoute
	{
		return self::getInstance()->addRoute( 'POST', $uri, $action );
	}

	/** PUT route. */
	public static function put( string $uri, callable|array|string $action ): ApiRoute
	{
		return self::getInstance()->addRoute( 'PUT', $uri, $action );
	}

	/** PATCH route. */
	public static function patch( string $uri, callable|array|string $action ): ApiRoute
	{
		return self::getInstance()->addRoute( 'PATCH', $uri, $action );
	}

	/** DELETE route. */
	public static function delete( string $uri, callable|array|string $action ): ApiRoute
	{
		return self::getInstance()->addRoute( 'DELETE', $uri, $action );
	}

	/**
	 * Route for multiple methods.
	 *
	 * @param array<int, string> $methods  e.g. ['GET', 'POST']
	 */
	public static function match( array $methods, string $uri, callable|array|string $action ): ApiRoute
	{
		return self::getInstance()->addRoute( $methods, $uri, $action );
	}

	/** Route for any method. */
	public static function any( string $uri, callable|array|string $action ): ApiRoute
	{
		return self::getInstance()->addRoute( [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ], $uri, $action );
	}

	public function addRoute( string|array $methods, string $uri, callable|array|string $action ): ApiRoute
	{
		$route = new ApiRoute( $methods, $uri, $action );
		$this->routes[] = $route;
		return $route;
	}

	/**
	 * Set REST API namespace (e.g. plugitify/v1). URL: /wp-json/{namespace}/{route}.
	 */
	public function setNamespace( string $namespace ): self
	{
		$this->namespace = trim( $namespace, '/' );
		return $this;
	}

	/**
	 * Set prefix for all routes (e.g. 'admin' => routes become /wp-json/ns/admin/...).
	 */
	public function setPrefix( string $prefix ): self
	{
		$this->routePrefix = trim( $prefix, '/' );
		return $this;
	}

	/**
	 * Set resolver for controller action (e.g. resolve from container).
	 *
	 * @param callable $resolver  function( ApiRoute $route ): callable
	 */
	public function setActionResolver( callable $resolver ): self
	{
		$this->actionResolver = $resolver;
		return $this;
	}

	/**
	 * Register all routes with WordPress REST API. Call once (e.g. on rest_api_init).
	 */
	public function boot(): void
	{
		add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ], 10, 0 );
	}

	/**
	 * Called on rest_api_init. Registers each route via register_rest_route().
	 * WordPress expects $args as array of endpoint configs (one per method).
	 */
	public function registerRestRoutes(): void
	{
		$prefix = $this->routePrefix !== '' ? $this->routePrefix . '/' : '';
		$byPath = [];

		foreach ( $this->routes as $route ) {
			$path = $route->toWpRestRoute();
			$path = $prefix . $path;
			foreach ( $route->getMethods() as $method ) {
				$wpMethod = $this->mapMethodToWp( $method );
				if ( $wpMethod === null ) {
					continue;
				}
				if ( ! isset( $byPath[ $path ] ) ) {
					$byPath[ $path ] = [];
				}
				$byPath[ $path ][] = [
					'methods'             => $wpMethod,
					'callback'            => $this->wrapCallback( $route ),
					'permission_callback' => $this->buildPermissionCallback( $route ),
				];
			}
		}

		foreach ( $byPath as $path => $endpoints ) {
			register_rest_route( $this->namespace, $path, $endpoints );
		}
	}

	/**
	 * Get full REST URL for a path (e.g. for redirects or links).
	 *
	 * @param string $path  e.g. users or users/1
	 * @return string  e.g. https://site.com/wp-json/plugitify/v1/users
	 */
	public function restUrl( string $path = '' ): string
	{
		$path = trim( $path, '/' );
		$base = rest_url( $this->namespace . ( $this->routePrefix !== '' ? '/' . $this->routePrefix : '' ) );
		return $path !== '' ? $base . '/' . $path : $base;
	}

	/**
	 * Get route by name (if you set ->name() on routes).
	 */
	public function getRouteByName( string $name ): ?ApiRoute
	{
		foreach ( $this->routes as $route ) {
			if ( $route->getName() === $name ) {
				return $route;
			}
		}
		return null;
	}

	/**
	 * Build permission_callback for a route. Tool enable/disable is checked inside handlers via ToolsPolicy.
	 *
	 * @return callable(\WP_REST_Request): bool|\WP_Error
	 */
	private function buildPermissionCallback( ApiRoute $route ): callable
	{
		$basePermission = $route->getPermissionCallback();
		return $basePermission ?? '__return_true';
	}

	/**
	 * Map HTTP method to WordPress REST Server constant.
	 */
	private function mapMethodToWp( string $method ): ?string
	{
		$method = strtoupper( $method );
		if ( $method === 'ANY' ) {
			return \WP_REST_Server::ALLMETHODS;
		}
		$map = [
			'GET'    => \WP_REST_Server::READABLE,
			'POST'   => \WP_REST_Server::CREATABLE,
			'PUT'    => \WP_REST_Server::EDITABLE,
			'PATCH'  => \WP_REST_Server::EDITABLE,
			'DELETE' => \WP_REST_Server::DELETABLE,
		];
		return $map[ $method ] ?? null;
	}

	/**
	 * Wrap our action so it receives Request + params and returns WP_REST_Response-compatible.
	 *
	 * @return callable(WP_REST_Request): WP_REST_Response|WP_Error
	 */
	private function wrapCallback( ApiRoute $route ): callable
	{
		$resolver = $this->actionResolver;
		$action   = $route->resolveAction( $resolver );
		$paramNames = $route->getParameterNames();

		return function ( \WP_REST_Request $wpRequest ) use ( $action, $paramNames ) {
			$request = self::requestFromWpRest( $wpRequest );
			$params  = [];
			foreach ( $paramNames as $name ) {
				$params[ $name ] = $wpRequest->get_param( $name );
			}
			foreach ( $params as $key => $value ) {
				$request->setAttribute( $key, $value );
			}

			$result = $action( $request, ...array_values( $params ) );

			return rest_ensure_response( $result );
		};
	}

	/**
	 * Build our Request from WP_REST_Request (for use in controllers).
	 */
	public static function requestFromWpRest( \WP_REST_Request $wpRequest ): Request
	{
		$query  = $wpRequest->get_query_params();
		$body   = $wpRequest->get_body_params();
		$json   = $wpRequest->get_json_params();
		if ( is_array( $json ) ) {
			$body = array_merge( $body, $json );
		}
		$urlParams = $wpRequest->get_url_params();
		$query     = array_merge( $query, $urlParams );

		$server = $_SERVER ?? [];
		$server['REQUEST_METHOD'] = $wpRequest->get_method();

		$request = new Request( $query, $body, $_COOKIE ?? [], $_FILES ?? [], $server );
		return $request;
	}
}
