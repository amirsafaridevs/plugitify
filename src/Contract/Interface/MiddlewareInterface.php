<?php

namespace Plugifity\Contract\Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Plugifity\Core\Http\Request;

/**
 * Middleware contract (Laravel-style). Handle request and optionally pass to next.
 */
interface MiddlewareInterface
{
	/**
	 * Process the request. Call $next($request) to continue; return or exit to stop.
	 *
	 * @param Request $request
	 * @param callable $next  Next callable in pipeline: function (Request $request): mixed
	 * @return mixed
	 */
	public function handle( Request $request, callable $next ): mixed;
}
