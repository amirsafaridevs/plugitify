<?php

namespace Plugifity\Core\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Plugifity\Core\Validation\Validator;
use Plugifity\Core\Validation\ValidationException;

/**
 * Laravel-style HTTP Request: input, query, post, headers, method, files, validation.
 *
 * Usage:
 *   $request = Request::capture();
 *   $request->input( 'email' );
 *   $request->get( 'page', 1 );
 *   $request->validate( [ 'email' => 'required|email' ] );
 */
class Request
{
	/** @var array<string, mixed> */
	private array $query;

	/** @var array<string, mixed> */
	private array $request;

	/** @var array<string, mixed> */
	private array $cookies;

	/** @var array<string, mixed> */
	private array $files;

	/** @var array<string, mixed> */
	private array $server;

	/** @var array<string, mixed> Merged query + request (like $_REQUEST). */
	private array $input;

	/** @var string|null */
	private ?string $content = null;

	/** @var array<string, mixed> User-set attributes (e.g. route params). */
	private array $attributes = [];

	/**
	 * @param array<string, mixed> $query  GET
	 * @param array<string, mixed> $request POST / body
	 * @param array<string, mixed> $cookies
	 * @param array<string, mixed> $files
	 * @param array<string, mixed> $server  $_SERVER
	 */
	public function __construct(
		array $query = [],
		array $request = [],
		array $cookies = [],
		array $files = [],
		array $server = []
	) {
		$this->query   = $query;
		$this->request = $request;
		$this->cookies = $cookies;
		$this->files   = $this->normalizeFiles( $files );
		$this->server = $server;
		$this->input  = array_merge( $this->query, $this->request );
	}

	/**
	 * Create request from PHP globals.
	 */
	public static function capture(): self
	{
		return new self(
			$_GET,
			$_POST,
			$_COOKIE ?? [],
			$_FILES ?? [],
			$_SERVER ?? []
		);
	}

	/**
	 * Create from custom arrays (testing or sub-requests).
	 *
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $server
	 */
	public static function create( array $input = [], array $server = [] ): self
	{
		$get  = $server['REQUEST_METHOD'] === 'GET' ? $input : [];
		$post = $server['REQUEST_METHOD'] !== 'GET' ? $input : [];
		return new self( $get, $post, [], [], $server );
	}

	// -------------------------------------------------------------------------
	// Input
	// -------------------------------------------------------------------------

	/**
	 * Get input value (GET then POST). Dot notation supported.
	 *
	 * @param string|null $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function input( ?string $key = null, $default = null )
	{
		if ( $key === null ) {
			return $this->input;
		}
		return $this->getFrom( $this->input, $key, $default );
	}

	/**
	 * Get query (GET) value.
	 *
	 * @param string|null $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function query( ?string $key = null, $default = null )
	{
		if ( $key === null ) {
			return $this->query;
		}
		return $this->getFrom( $this->query, $key, $default );
	}

	/**
	 * Get request (POST) value.
	 *
	 * @param string|null $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function post( ?string $key = null, $default = null )
	{
		if ( $key === null ) {
			return $this->request;
		}
		return $this->getFrom( $this->request, $key, $default );
	}

	/** Alias for input(). */
	public function get( ?string $key = null, $default = null )
	{
		return $this->input( $key, $default );
	}

	/**
	 * Get all input (optionally only given keys).
	 *
	 * @param array<int, string>|null $keys
	 * @return array<string, mixed>
	 */
	public function all( ?array $keys = null ): array
	{
		$input = $this->input;
		if ( $keys !== null ) {
			return $this->only( $keys );
		}
		return $input;
	}

	/**
	 * Get only specified keys from input. Dot notation supported for keys.
	 *
	 * @param array<int, string>|string $keys
	 * @return array<string, mixed>
	 */
	public function only( array|string $keys ): array
	{
		$keys = is_array( $keys ) ? $keys : [ $keys ];
		$out  = [];
		foreach ( $keys as $key ) {
			$key         = (string) $key;
			$out[ $key ] = $this->input( $key );
		}
		return $out;
	}

	/**
	 * Get input except specified keys.
	 *
	 * @param array<int, string>|string $keys
	 * @return array<string, mixed>
	 */
	public function except( array|string $keys ): array
	{
		$keys = is_array( $keys ) ? $keys : func_get_args();
		$keys = array_flip( array_map( 'strval', $keys ) );
		return array_diff_key( $this->input, $keys );
	}

	/**
	 * Check if key exists (and not empty string).
	 *
	 * @param string|array<int, string> $key
	 */
	public function has( string|array $key ): bool
	{
		$keys = is_array( $key ) ? $key : [ $key ];
		foreach ( $keys as $k ) {
			$v = $this->input( $k );
			if ( $v === null || $v === '' ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if key is present and not empty (filled).
	 *
	 * @param string|array<int, string> $key
	 */
	public function filled( string|array $key ): bool
	{
		$keys = is_array( $key ) ? $key : [ $key ];
		foreach ( $keys as $k ) {
			$v = $this->input( $k );
			if ( $v === null || $v === '' || ( is_array( $v ) && $v === [] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if key is missing.
	 */
	public function missing( string|array $key ): bool
	{
		return ! $this->has( $key );
	}

	/**
	 * Get boolean input (1, "1", true, "on", "yes").
	 */
	public function boolean( string $key, bool $default = false ): bool
	{
		$v = $this->input( $key );
		if ( $v === null || $v === '' ) {
			return $default;
		}
		return in_array( $v, [ true, 1, '1', 'on', 'yes' ], true );
	}

	/**
	 * Get integer input.
	 */
	public function integer( string $key, int $default = 0 ): int
	{
		$v = $this->input( $key );
		if ( $v === null || $v === '' ) {
			return $default;
		}
		return (int) $v;
	}

	/**
	 * Get string input (trimmed).
	 */
	public function str( string $key, string $default = '' ): string
	{
		$v = $this->input( $key );
		if ( $v === null || ( is_array( $v ) ) ) {
			return $default;
		}
		return trim( (string) $v );
	}

	// -------------------------------------------------------------------------
	// Method & headers
	// -------------------------------------------------------------------------

	public function method(): string
	{
		$m = $this->server( 'REQUEST_METHOD', 'GET' );
		if ( $m === 'POST' && (string) $this->input( '_method' ) !== '' ) {
			$override = strtoupper( (string) $this->input( '_method' ) );
			if ( in_array( $override, [ 'PUT', 'PATCH', 'DELETE' ], true ) ) {
				return $override;
			}
		}
		return strtoupper( $m );
	}

	public function isMethod( string $method ): bool
	{
		return strtoupper( $method ) === $this->method();
	}

	public function isGet(): bool
	{
		return $this->isMethod( 'GET' );
	}

	public function isPost(): bool
	{
		return $this->isMethod( 'POST' );
	}

	public function isPut(): bool
	{
		return $this->isMethod( 'PUT' );
	}

	public function isPatch(): bool
	{
		return $this->isMethod( 'PATCH' );
	}

	public function isDelete(): bool
	{
		return $this->isMethod( 'DELETE' );
	}

	public function isAjax(): bool
	{
		return strtolower( (string) $this->server( 'HTTP_X_REQUESTED_WITH', '' ) ) === 'xmlhttprequest';
	}

	public function isJson(): bool
	{
		return str_contains( (string) $this->header( 'Content-Type' ), 'application/json' );
	}

	/**
	 * Get header (case-insensitive). Without HTTP_ prefix.
	 *
	 * @param string $key e.g. "Content-Type"
	 * @param string|null $default
	 * @return string|null
	 */
	public function header( string $key, ?string $default = null ): ?string
	{
		$key = 'HTTP_' . strtoupper( str_replace( [ '-', ' ' ], '_', $key ) );
		$v  = $this->server( $key );
		return $v !== null ? (string) $v : $default;
	}

	/**
	 * Get server var.
	 *
	 * @param string|null $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function server( ?string $key = null, $default = null )
	{
		if ( $key === null ) {
			return $this->server;
		}
		$key = strtoupper( str_replace( [ '.', '-', ' ' ], '_', $key ) );
		return $this->server[ $key ] ?? $default;
	}

	/**
	 * Get cookie.
	 *
	 * @param string|null $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function cookie( ?string $key = null, $default = null )
	{
		if ( $key === null ) {
			return $this->cookies;
		}
		return $this->cookies[ $key ] ?? $default;
	}

	/**
	 * Bearer token from Authorization header.
	 */
	public function bearerToken(): ?string
	{
		$header = $this->header( 'Authorization' );
		if ( $header !== null && str_starts_with( $header, 'Bearer ' ) ) {
			return trim( substr( $header, 7 ) );
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// URL / path
	// -------------------------------------------------------------------------

	public function url(): string
	{
		$https = $this->server( 'HTTPS' );
		$scheme = ( $https && strtolower( (string) $https ) !== 'off' ) ? 'https' : 'http';
		$host   = $this->server( 'HTTP_HOST', 'localhost' );
		$uri    = $this->server( 'REQUEST_URI', '/' );
		// Remove query string from URI for "url without query"
		$path   = str_contains( $uri, '?' ) ? substr( $uri, 0, strpos( $uri, '?' ) ) : $uri;
		return $scheme . '://' . $host . $path;
	}

	public function fullUrl(): string
	{
		$url = $this->url();
		$qs  = $this->server( 'QUERY_STRING' );
		if ( $qs !== null && $qs !== '' ) {
			return $url . '?' . $qs;
		}
		return $url;
	}

	public function path(): string
	{
		$uri = $this->server( 'REQUEST_URI', '/' );
		$path = parse_url( $uri, PHP_URL_PATH );
		return $path !== null ? $path : '/';
	}

	/**
	 * Check if path matches pattern (e.g. "admin/*").
	 */
	public function is( string $pattern ): bool
	{
		$path = $this->path();
		$pattern = preg_quote( $pattern, '#' );
		$pattern = str_replace( '\*', '.*', $pattern );
		return (bool) preg_match( '#^' . $pattern . '$#', $path );
	}

	// -------------------------------------------------------------------------
	// Files
	// -------------------------------------------------------------------------

	/**
	 * Get uploaded file(s). For single file returns array with name, type, tmp_name, error, size.
	 *
	 * @param string|null $key
	 * @return array<string, mixed>|array<int, array<string, mixed>>|null
	 */
	public function file( ?string $key = null )
	{
		if ( $key === null ) {
			return $this->files;
		}
		return $this->getFrom( $this->files, $key, null );
	}

	public function hasFile( string $key ): bool
	{
		$file = $this->file( $key );
		if ( $file === null ) {
			return false;
		}
		if ( isset( $file['error'] ) ) {
			return (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
		}
		foreach ( $file as $f ) {
			if ( isset( $f['error'] ) && (int) $f['error'] !== UPLOAD_ERR_NO_FILE ) {
				return true;
			}
		}
		return false;
	}

	// -------------------------------------------------------------------------
	// JSON / content
	// -------------------------------------------------------------------------

	/**
	 * Get raw body (e.g. JSON string).
	 */
	public function getContent(): ?string
	{
		if ( $this->content === null ) {
			$this->content = file_get_contents( 'php://input' ) ?: null;
		}
		return $this->content;
	}

	/**
	 * Decode JSON body. Returns associative array or null.
	 *
	 * @return array<string, mixed>|null
	 */
	public function json(): ?array
	{
		$content = $this->getContent();
		if ( $content === null || $content === '' ) {
			return null;
		}
		$decoded = json_decode( $content, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	// -------------------------------------------------------------------------
	// Merge / attributes
	// -------------------------------------------------------------------------

	/**
	 * Merge extra input (e.g. route params). Does not replace existing.
	 *
	 * @param array<string, mixed> $data
	 */
	public function merge( array $data ): self
	{
		$this->input = array_merge( $this->input, $data );
		return $this;
	}

	/**
	 * Replace input with given array.
	 *
	 * @param array<string, mixed> $data
	 */
	public function replace( array $data ): self
	{
		$this->input = $data;
		return $this;
	}

	/**
	 * Set request attribute (e.g. route param).
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function setAttribute( string $key, $value ): self
	{
		$this->attributes[ $key ] = $value;
		return $this;
	}

	/**
	 * Get request attribute.
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getAttribute( string $key, $default = null )
	{
		return $this->attributes[ $key ] ?? $default;
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Validate request input. Throws ValidationException on failure.
	 *
	 * @param array<string, string|array> $rules
	 * @param array<string, string> $messages
	 * @param array<string, string> $attributes
	 * @return array<string, mixed> Validated data
	 * @throws ValidationException
	 */
	public function validate( array $rules, array $messages = [], array $attributes = [] ): array
	{
		$validator = Validator::make( $this->all(), $rules, $messages, $attributes );
		return $validator->validate();
	}

	/**
	 * Run validator and get validator instance (no throw).
	 *
	 * @param array<string, string|array> $rules
	 * @param array<string, string> $messages
	 * @param array<string, string> $attributes
	 * @return Validator
	 */
	public function validator( array $rules, array $messages = [], array $attributes = [] ): Validator
	{
		return Validator::make( $this->all(), $rules, $messages, $attributes );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get value from array with dot notation.
	 *
	 * @param array<string, mixed> $arr
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	private function getFrom( array $arr, string $key, $default = null )
	{
		$keys = explode( '.', $key );
		$v = $arr;
		foreach ( $keys as $k ) {
			if ( ! is_array( $v ) || ! array_key_exists( $k, $v ) ) {
				return $default;
			}
			$v = $v[ $k ];
		}
		return $v;
	}

	/**
	 * Normalize $_FILES structure (single vs multiple).
	 *
	 * @param array<string, mixed> $files
	 * @return array<string, mixed>
	 */
	private function normalizeFiles( array $files ): array
	{
		$out = [];
		foreach ( $files as $key => $file ) {
			if ( isset( $file['name'] ) && is_array( $file['name'] ) ) {
				$out[ $key ] = [];
				foreach ( array_keys( $file['name'] ) as $i ) {
					$out[ $key ][ $i ] = [
						'name'     => $file['name'][ $i ],
						'type'     => $file['type'][ $i ],
						'tmp_name' => $file['tmp_name'][ $i ],
						'error'    => $file['error'][ $i ],
						'size'     => $file['size'][ $i ],
					];
				}
			} else {
				$out[ $key ] = $file;
			}
		}
		return $out;
	}
}
