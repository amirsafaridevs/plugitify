<?php

namespace Plugifity\Core\Http\Middleware;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Plugifity\Contract\Interface\MiddlewareInterface;
use Plugifity\Core\Http\Request;

/**
 * Verify WordPress nonce for POST requests (Laravel-style middleware).
 */
class VerifyNonce implements MiddlewareInterface
{
	private string $action = 'plugitify_nonce';

	private string $queryKey = '_wpnonce';

	public function __construct( ?string $action = null, ?string $queryKey = null )
	{
		if ( $action !== null ) {
			$this->action = $action;
		}
		if ( $queryKey !== null ) {
			$this->queryKey = $queryKey;
		}
	}

	public function handle( Request $request, callable $next ): mixed
	{
		if ( ! $request->isPost() ) {
			return $next( $request );
		}

		$nonce = $request->input( $this->queryKey ) ?: $request->header( 'X-WP-Nonce' );
		if ( $nonce === null || ! wp_verify_nonce( (string) $nonce, $this->action ) ) {
			if ( wp_doing_ajax() || $request->isAjax() ) {
				wp_send_json_error( [ 'message' => __( 'Security check failed.', 'plugitify' ) ], 403 );
			}
			wp_die( esc_html__( 'Security check failed.', 'plugitify' ), 403 );
		}

		return $next( $request );
	}
}
