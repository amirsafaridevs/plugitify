<?php

namespace Plugifity\Core\Http\Middleware;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Plugifity\Contract\Interface\MiddlewareInterface;
use Plugifity\Core\Http\Request;

/**
 * Runs a stack of middlewares then the final action (Laravel-style pipeline).
 */
class Pipeline
{
	/**
	 * @param Request $request
	 * @param array<int, MiddlewareInterface|class-string> $middlewares
	 * @param callable $action  function (Request $request, array $params): mixed
	 * @param array<string, mixed> $params  Route parameters
	 * @param callable|null $resolver  Optional. function (string $class): MiddlewareInterface
	 * @return mixed
	 */
	public function run(
		Request $request,
		array $middlewares,
		callable $action,
		array $params = [],
		?callable $resolver = null
	) {
		$resolver = $resolver ?? function ( $class ) {
			return is_object( $class ) ? $class : new $class();
		};

		$next = function ( Request $req ) use ( $action, $params ) {
			return $action( $req, $params );
		};

		$stack = array_reverse( $middlewares );
		foreach ( $stack as $middleware ) {
			$instance = $resolver( $middleware );
			if ( ! $instance instanceof MiddlewareInterface ) {
				continue;
			}
			$next = function ( Request $req ) use ( $instance, $next ) {
				return $instance->handle( $req, $next );
			};
		}

		return $next( $request );
	}
}
