<?php

namespace Plugifity\Core\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Plugifity\Contract\Interface\MiddlewareInterface;
use Plugifity\Core\Application;
use Plugifity\Core\Http\Middleware\Pipeline;

/**
 * Laravel-style Router using WordPress rewrite rules, query_vars, and template_include.
 *
 * Usage:
 *   Router::get( '/dashboard', [ DashboardController::class, 'index' ] )->name( 'dashboard' )->middleware( 'auth' );
 *   Router::post( '/save', fn( Request $r ) => ... )->middleware( 'nonce' );
 *   Then call Router::bootWordPress() once (e.g. from a service provider).
 */
class Router
{
	/** @var array<int, Route> */
	private array $routes = [];

	/** @var array<string, string|class-string<MiddlewareInterface>> */
	private array $middlewareAliases = [];

	private string $routePrefix = 'plugitify';

	private string $routeQueryVar = 'plugitify_route';

	private string $paramQueryVarPrefix = 'plugitify_';

	/** @var callable|null Resolver for controller action: function (Route): callable */
	private $actionResolver = null;

	/** @var callable|null Resolver for middleware: function (string): MiddlewareInterface */
	private $middlewareResolver = null;

	private static ?self $instance = null;

	public static function getInstance(): self
	{
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register GET route.
	 *
	 * @param string $uri
	 * @param callable|array{0: class-string|object, 1: string} $action
	 */
	public static function get( string $uri, callable|array $action ): Route
	{
		return self::getInstance()->addRoute( 'GET', $uri, $action );
	}

	/**
	 * Register POST route.
	 */
	public static function post( string $uri, callable|array $action ): Route
	{
		return self::getInstance()->addRoute( 'POST', $uri, $action );
	}

	/**
	 * Register PUT route.
	 */
	public static function put( string $uri, callable|array $action ): Route
	{
		return self::getInstance()->addRoute( 'PUT', $uri, $action );
	}

	/**
	 * Register PATCH route.
	 */
	public static function patch( string $uri, callable|array $action ): Route
	{
		return self::getInstance()->addRoute( 'PATCH', $uri, $action );
	}

	/**
	 * Register DELETE route.
	 */
	public static function delete( string $uri, callable|array $action ): Route
	{
		return self::getInstance()->addRoute( 'DELETE', $uri, $action );
	}

	/**
	 * Register route for any method.
	 */
	public static function any( string $uri, callable|array $action ): Route
	{
		return self::getInstance()->addRoute( 'ANY', $uri, $action );
	}

	/**
	 * Add WordPress rewrite endpoint (uses add_rewrite_endpoint).
	 *
	 * @param string $name    Endpoint name (e.g. 'edit').
	 * @param int    $places  EP_PERMALINK, EP_PAGES, etc.
	 * @param string|null $queryVar Optional query var name.
	 */
	public static function endpoint( string $name, int $places = EP_PERMALINK, ?string $queryVar = null ): void
	{
		add_rewrite_endpoint( $name, $places, $queryVar );
	}

	public function addRoute( string $method, string $uri, callable|array $action ): Route
	{
		$route = new Route( $method, $uri, $action );
		$this->routes[] = $route;
		return $route;
	}

	/**
	 * Register middleware alias (name => class or instance).
	 *
	 * @param string $name
	 * @param string|object $middleware
	 */
	public function registerMiddleware( string $name, string|object $middleware ): self
	{
		$this->middlewareAliases[ $name ] = $middleware;
		return $this;
	}

	/**
	 * Set URL prefix for all routes (e.g. 'plugitify' => /plugitify/dashboard).
	 */
	public function setPrefix( string $prefix ): self
	{
		$this->routePrefix = trim( $prefix, '/' );
		return $this;
	}

	/**
	 * Set custom query var names (route var and param prefix).
	 */
	public function setQueryVars( string $routeVar, string $paramPrefix ): self
	{
		$this->routeQueryVar     = $routeVar;
		$this->paramQueryVarPrefix = $paramPrefix;
		return $this;
	}

	/**
	 * Set resolver for controller@method actions (e.g. resolve from container).
	 */
	public function setActionResolver( callable $resolver ): self
	{
		$this->actionResolver = $resolver;
		return $this;
	}

	/**
	 * Set resolver for middleware (e.g. resolve from container by name).
	 */
	public function setMiddlewareResolver( callable $resolver ): self
	{
		$this->middlewareResolver = $resolver;
		return $this;
	}

	/**
	 * Hook into WordPress: register rewrite rules, query_vars, template_redirect, template_include.
	 * Call once (e.g. from App or a RouteServiceProvider).
	 */
	public function bootWordPress(): void
	{
		add_action( 'init', [ $this, 'registerRewriteRules' ], 10, 0 );
		add_filter( 'query_vars', [ $this, 'filterQueryVars' ], 10, 1 );
		add_action( 'template_redirect', [ $this, 'dispatch' ], 5 );
		add_filter( 'template_include', [ $this, 'filterTemplateInclude' ], 99 );
	}

	/**
	 * Register rewrite rules for all routes (WordPress init).
	 * One rule per unique path (same path GET/POST share one rule; method checked in dispatch).
	 */
	public function registerRewriteRules(): void
	{
		$added = [];
		foreach ( $this->routes as $route ) {
			$pathKey = $route->getPathKey();
			if ( isset( $added[ $pathKey ] ) ) {
				continue;
			}
			$added[ $pathKey ] = true;
			$regex   = $route->toRewriteRegex( $this->routePrefix );
			$redirect = $route->toRewriteRedirect( $this->routeQueryVar, $this->paramQueryVarPrefix );
			add_rewrite_rule( $regex, $redirect, 'top' );
		}
	}

	/**
	 * Add our query vars to WordPress.
	 *
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function filterQueryVars( array $vars ): array
	{
		$vars[] = $this->routeQueryVar;
		for ( $i = 1; $i <= 10; $i++ ) {
			$vars[] = $this->paramQueryVarPrefix . $i;
		}
		return $vars;
	}

	/**
	 * Dispatch current request if our route query var is set.
	 */
	public function dispatch(): void
	{
		$pathKey = get_query_var( $this->routeQueryVar, null );
		if ( $pathKey === null || $pathKey === '' ) {
			return;
		}

		$requestMethod = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
		$route = $this->findRouteByPathKeyAndMethod( $pathKey, $requestMethod );
		if ( $route === null ) {
			return;
		}

		$params = $this->getRouteParams( $route );
		$request = Request::capture();
		foreach ( $params as $key => $value ) {
			$request->setAttribute( $key, $value );
		}

		$middlewares = $this->resolveMiddlewares( $route->getMiddleware() );
		$action      = $route->resolveAction( $this->actionResolver );

		$pipeline = new Pipeline();
		$result   = $pipeline->run( $request, $middlewares, $action, $params, $this->getMiddlewareResolver() );

		// If action returned a template path, store for template_include filter.
		if ( is_string( $result ) && $result !== '' ) {
			$this->matchedTemplate = $result;
			return;
		}

		// If action already sent output and exited, we're done.
	}

	/** @var string|null Set by dispatch() when route action returns a template path. */
	private ?string $matchedTemplate = null;

	/**
	 * Let WordPress use our template when a route returned one.
	 *
	 * @param string $template
	 * @return string
	 */
	public function filterTemplateInclude( string $template ): string
	{
		if ( $this->matchedTemplate !== null && is_file( $this->matchedTemplate ) ) {
			return $this->matchedTemplate;
		}
		return $template;
	}

	/**
	 * Flush rewrite rules (call on plugin activation so routes are registered).
	 */
	public function flushRewriteRules(): void
	{
		flush_rewrite_rules();
	}

	/**
	 * Get route by name.
	 */
	public function getRouteByName( string $name ): ?Route
	{
		return $this->findRouteByName( $name );
	}

	/**
	 * Generate URL for a named route (with optional params).
	 *
	 * @param string $name
	 * @param array<string, string|int> $params
	 * @return string
	 */
	public function route( string $name, array $params = [] ): string
	{
		$route = $this->findRouteByName( $name );
		if ( $route === null ) {
			return home_url( '/' );
		}
		$uri = $route->getUri();
		foreach ( $params as $key => $value ) {
			$uri = str_replace( '{' . $key . '}', (string) $value, $uri );
		}
		$prefix = $this->routePrefix ? '/' . $this->routePrefix : '';
		return home_url( $prefix . $uri );
	}

	private function findRouteByPathKeyAndMethod( string $pathKey, string $requestMethod ): ?Route
	{
		foreach ( $this->routes as $route ) {
			if ( $route->getPathKey() !== $pathKey ) {
				continue;
			}
			if ( $route->getMethod() === 'ANY' || $route->getMethod() === $requestMethod ) {
				return $route;
			}
		}
		return null;
	}

	private function findRouteByName( string $name ): ?Route
	{
		foreach ( $this->routes as $route ) {
			if ( $route->getName() === $name ) {
				return $route;
			}
		}
		return null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getRouteParams( Route $route ): array
	{
		$names = $route->getParameterNames();
		$out   = [];
		foreach ( $names as $i => $name ) {
			$key = $this->paramQueryVarPrefix . ( $i + 1 );
			$val = get_query_var( $key, null );
			if ( $val !== null && $val !== '' ) {
				$out[ $name ] = $val;
			}
		}
		return $out;
	}

	/**
	 * @param array<int, string> $names
	 * @return array<int, string|object>
	 */
	private function resolveMiddlewares( array $names ): array
	{
		$out = [];
		foreach ( $names as $name ) {
			$resolved = $this->middlewareAliases[ $name ] ?? $name;
			$out[] = $resolved;
		}
		return $out;
	}

	private function getMiddlewareResolver(): ?callable
	{
		if ( $this->middlewareResolver !== null ) {
			return $this->middlewareResolver;
		}
		$app       = Application::get();
		$container = $app->getContainer();
		$aliases   = &$this->middlewareAliases;
		return function ( $classOrName ) use ( $container, $aliases ) {
			if ( is_object( $classOrName ) ) {
				return $classOrName;
			}
			$resolved = $aliases[ $classOrName ] ?? $classOrName;
			if ( is_object( $resolved ) ) {
				return $resolved;
			}
			if ( $container->bound( $resolved ) ) {
				return $container->get( $resolved );
			}
			return new $resolved();
		};
	}
}
