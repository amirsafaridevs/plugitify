<?php

namespace Plugifity\Core\Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Laravel-style Validator: rules, custom messages, custom attributes.
 *
 * Usage:
 *   $v = Validator::make( $data, [ 'email' => 'required|email', 'name' => 'required|string|max:255' ], $messages, $attributes );
 *   if ( $v->fails() ) { ... $v->errors()->all(); }
 *   $safe = $v->validated();
 *
 * Supported rules: required, nullable, sometimes, filled, present, accepted,
 * string, integer, numeric, boolean, array, email, url, ip, ipv4, ipv6,
 * alpha, alpha_num, alpha_dash, min:N, max:N, between:min,max, size:N,
 * in:a,b,c, not_in:a,b,c, regex:pattern, same:field, different:field,
 * confirmed, date, date_after:date, date_before:date, date_equals:date,
 * date_after_or_equal:date, date_before_or_equal:date, json,
 * required_if:field,value, required_unless:field,v1,v2, required_with:f1,f2,
 * required_without:f1,f2, required_with_all:f1,f2, required_without_all:f1,f2.
 */
class Validator
{
	/** @var array<string, mixed> */
	private array $data;

	/** @var array<string, array<int, array{0: string, 1: array}> */
	private array $rules;

	/** @var array<string, string> */
	private array $messages;

	/** @var array<string, string> */
	private array $customAttributes;

	/** @var array<string, array<int, string>> */
	private array $errors = [];

	/** @var array<string, mixed> */
	private array $validated = [];

	private bool $ran = false;

	/** @var array<string, string> */
	private static array $defaultMessages = [];

	/**
	 * Create validator instance.
	 *
	 * @param array<string, mixed> $data
	 * @param array<string, string|array> $rules  e.g. [ 'email' => 'required|email', 'name' => [ 'required', 'max:255' ] ]
	 * @param array<string, string> $messages  e.g. [ 'email.required' => '...', 'required' => '...' ]
	 * @param array<string, string> $attributes  e.g. [ 'email' => 'Email address' ]
	 */
	public function __construct( array $data, array $rules, array $messages = [], array $attributes = [] )
	{
		$this->data = $data;
		$this->rules = $this->parseRules( $rules );
		$this->messages = $messages;
		$this->customAttributes = $attributes;
		$this->initDefaultMessages();
	}

	/**
	 * Build validator instance (static factory).
	 *
	 * @param array<string, mixed> $data
	 * @param array<string, string|array> $rules
	 * @param array<string, string> $messages
	 * @param array<string, string> $attributes
	 * @return self
	 */
	public static function make( array $data, array $rules, array $messages = [], array $attributes = [] ): self
	{
		return new self( $data, $rules, $messages, $attributes );
	}

	/**
	 * Run validation and return whether it passed.
	 */
	public function passes(): bool
	{
		$this->run();
		return empty( $this->errors );
	}

	/**
	 * Run validation and return whether it failed.
	 */
	public function fails(): bool
	{
		return ! $this->passes();
	}

	/**
	 * Get errors (MessageBag-like).
	 *
	 * @return MessageBag
	 */
	public function errors(): MessageBag
	{
		$this->run();
		return new MessageBag( $this->errors );
	}

	/**
	 * Get only validated (passed) data.
	 *
	 * @return array<string, mixed>
	 */
	public function validated(): array
	{
		$this->run();
		return $this->validated;
	}

	/**
	 * Alias for validated().
	 *
	 * @return array<string, mixed>
	 */
	public function safe(): array
	{
		return $this->validated();
	}

	/**
	 * Validate and throw ValidationException on failure.
	 *
	 * @return array<string, mixed> Validated data.
	 * @throws ValidationException
	 */
	public function validate(): array
	{
		if ( $this->fails() ) {
			throw new ValidationException( $this );
		}
		return $this->validated();
	}

	/**
	 * Get raw errors array (for exception/backward use).
	 *
	 * @return array<string, array<int, string>>
	 */
	public function getErrors(): array
	{
		$this->run();
		return $this->errors;
	}

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	private function initDefaultMessages(): void
	{
		if ( self::$defaultMessages !== [] ) {
			return;
		}
		self::$defaultMessages = [
			'required'             => __( 'The :attribute field is required.', 'plugitify' ),
			'required_if'          => __( 'The :attribute field is required when :other is :value.', 'plugitify' ),
			'required_unless'      => __( 'The :attribute field is required unless :other is in :values.', 'plugitify' ),
			'required_with'        => __( 'The :attribute field is required when :values is present.', 'plugitify' ),
			'required_without'      => __( 'The :attribute field is required when :values is not present.', 'plugitify' ),
			'required_with_all'     => __( 'The :attribute field is required when :values are present.', 'plugitify' ),
			'required_without_all'  => __( 'The :attribute field is required when none of :values are present.', 'plugitify' ),
			'present'               => __( 'The :attribute field must be present.', 'plugitify' ),
			'filled'                 => __( 'The :attribute field must have a value.', 'plugitify' ),
			'accepted'              => __( 'The :attribute field must be accepted.', 'plugitify' ),
			'nullable'              => __( 'The :attribute field must be nullable.', 'plugitify' ),
			'string'                => __( 'The :attribute must be a string.', 'plugitify' ),
			'integer'               => __( 'The :attribute must be an integer.', 'plugitify' ),
			'numeric'               => __( 'The :attribute must be a number.', 'plugitify' ),
			'boolean'               => __( 'The :attribute field must be true or false.', 'plugitify' ),
			'array'                 => __( 'The :attribute must be an array.', 'plugitify' ),
			'email'                 => __( 'The :attribute must be a valid email address.', 'plugitify' ),
			'url'                   => __( 'The :attribute must be a valid URL.', 'plugitify' ),
			'ip'                    => __( 'The :attribute must be a valid IP address.', 'plugitify' ),
			'ipv4'                  => __( 'The :attribute must be a valid IPv4 address.', 'plugitify' ),
			'ipv6'                  => __( 'The :attribute must be a valid IPv6 address.', 'plugitify' ),
			'alpha'                 => __( 'The :attribute may only contain letters.', 'plugitify' ),
			'alpha_num'             => __( 'The :attribute may only contain letters and numbers.', 'plugitify' ),
			'alpha_dash'            => __( 'The :attribute may only contain letters, numbers, dashes and underscores.', 'plugitify' ),
			'min'                   => __( 'The :attribute must be at least :min.', 'plugitify' ),
			'max'                   => __( 'The :attribute must not be greater than :max.', 'plugitify' ),
			'between'               => __( 'The :attribute must be between :min and :max.', 'plugitify' ),
			'size'                  => __( 'The :attribute must be :size.', 'plugitify' ),
			'in'                    => __( 'The selected :attribute is invalid.', 'plugitify' ),
			'not_in'                => __( 'The selected :attribute is invalid.', 'plugitify' ),
			'regex'                 => __( 'The :attribute format is invalid.', 'plugitify' ),
			'same'                  => __( 'The :attribute and :other must match.', 'plugitify' ),
			'different'             => __( 'The :attribute and :other must be different.', 'plugitify' ),
			'confirmed'             => __( 'The :attribute confirmation does not match.', 'plugitify' ),
			'date'                  => __( 'The :attribute is not a valid date.', 'plugitify' ),
			'date_after'            => __( 'The :attribute must be a date after :date.', 'plugitify' ),
			'date_before'           => __( 'The :attribute must be a date before :date.', 'plugitify' ),
			'date_equals'           => __( 'The :attribute must be a date equal to :date.', 'plugitify' ),
			'date_after_or_equal'   => __( 'The :attribute must be a date after or equal to :date.', 'plugitify' ),
			'date_before_or_equal'  => __( 'The :attribute must be a date before or equal to :date.', 'plugitify' ),
			'dimensions'            => __( 'The :attribute has invalid image dimensions.', 'plugitify' ),
			'distinct'              => __( 'The :attribute field has a duplicate value.', 'plugitify' ),
			'json'                  => __( 'The :attribute must be a valid JSON string.', 'plugitify' ),
		];
	}

	/**
	 * @param array<string, string|array> $rules
	 * @return array<string, array<int, array{0: string, 1: array}>>
	 */
	private function parseRules( array $rules ): array
	{
		$parsed = [];
		foreach ( $rules as $attr => $rule ) {
			$list = is_array( $rule ) ? $rule : explode( '|', (string) $rule );
			$parsed[ $attr ] = [];
			foreach ( $list as $r ) {
				$r = trim( (string) $r );
				if ( $r === '' ) {
					continue;
				}
				$parts = explode( ':', $r, 2 );
				$name = $parts[0];
				$params = isset( $parts[1] ) ? array_map( 'trim', explode( ',', $parts[1] ) ) : [];
				$parsed[ $attr ][] = [ $name, $params ];
			}
		}
		return $parsed;
	}

	private function run(): void
	{
		if ( $this->ran ) {
			return;
		}
		$this->ran = true;
		$this->errors = [];
		$this->validated = [];

		foreach ( $this->rules as $attribute => $ruleList ) {
			$value = $this->getValue( $attribute );
			$isEmpty = $this->isEmpty( $value );

			foreach ( $ruleList as [ $ruleName, $params ] ) {
				// Skip other rules when value is empty and rule is nullable
				if ( $isEmpty && in_array( $ruleName, [ 'nullable', 'sometimes' ], true ) ) {
					continue;
				}
				if ( $isEmpty && $ruleName !== 'required' && $ruleName !== 'required_if' && $ruleName !== 'required_unless'
					&& $ruleName !== 'required_with' && $ruleName !== 'required_without' && $ruleName !== 'required_with_all'
					&& $ruleName !== 'required_without_all' && $ruleName !== 'filled' && $ruleName !== 'accepted' ) {
					continue;
				}

				$pass = $this->runRule( $ruleName, $params, $attribute, $value, $this->data );
				if ( ! $pass ) {
					$this->addError( $attribute, $ruleName, $params, $value );
					break; // one error per attribute by default (like Laravel)
				}
			}

			if ( ! isset( $this->errors[ $attribute ] ) ) {
				$this->validated[ $attribute ] = $value;
			}
		}
	}

	private function getValue( string $key )
	{
		$keys = explode( '.', $key );
		$v = $this->data;
		foreach ( $keys as $k ) {
			if ( ! is_array( $v ) || ! array_key_exists( $k, $v ) ) {
				return null;
			}
			$v = $v[ $k ];
		}
		return $v;
	}

	private function isEmpty( $value ): bool
	{
		if ( $value === null ) {
			return true;
		}
		if ( is_string( $value ) && trim( $value ) === '' ) {
			return true;
		}
		if ( is_array( $value ) && $value === [] ) {
			return true;
		}
		return false;
	}

	private function runRule( string $name, array $params, string $attribute, $value, array $data ): bool
	{
		switch ( $name ) {
			case 'required':
				return ! $this->isEmpty( $value );
			case 'nullable':
			case 'sometimes':
				return true;
			case 'filled':
				// When field is present it must not be empty. We only run if value was provided (required run first).
				return ! $this->isEmpty( $value );
			case 'accepted':
				return in_array( $value, [ 'yes', 'on', '1', 1, true ], true );
			case 'string':
				return is_string( $value ) || ( is_numeric( $value ) && (string) $value === (string)(int) $value );
			case 'integer':
				return filter_var( $value, FILTER_VALIDATE_INT ) !== false;
			case 'numeric':
				return is_numeric( $value );
			case 'boolean':
				return in_array( $value, [ true, false, 0, 1, '0', '1' ], true );
			case 'array':
				return is_array( $value );
			case 'email':
				return is_string( $value ) && filter_var( $value, FILTER_VALIDATE_EMAIL ) !== false;
			case 'url':
				return is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) !== false;
			case 'ip':
				return is_string( $value ) && filter_var( $value, FILTER_VALIDATE_IP ) !== false;
			case 'ipv4':
				return is_string( $value ) && filter_var( $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) !== false;
			case 'ipv6':
				return is_string( $value ) && filter_var( $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) !== false;
			case 'alpha':
				return is_string( $value ) && preg_match( '/^\pL+$/u', $value );
			case 'alpha_num':
				return is_string( $value ) && preg_match( '/^[\pL\pN]+$/u', $value );
			case 'alpha_dash':
				return is_string( $value ) && preg_match( '/^[\pL\pN_-]+$/u', $value );
			case 'min':
				$min = (int) ( $params[0] ?? 0 );
				return $this->sizeCompare( $value, $min, 'min' );
			case 'max':
				$max = (int) ( $params[0] ?? 0 );
				return $this->sizeCompare( $value, $max, 'max' );
			case 'between':
				$min = (int) ( $params[0] ?? 0 );
				$max = (int) ( $params[1] ?? 0 );
				return $this->sizeCompare( $value, $min, 'min' ) && $this->sizeCompare( $value, $max, 'max' );
			case 'size':
				$size = (int) ( $params[0] ?? 0 );
				return $this->getSize( $value ) === $size;
			case 'in':
				return in_array( $value, $params, true );
			case 'not_in':
				return ! in_array( $value, $params, true );
			case 'regex':
				$pattern = $params[0] ?? '';
				return is_string( $value ) && preg_match( $pattern, $value );
			case 'same':
				$other = $params[0] ?? '';
				return $value === $this->getValue( $other );
			case 'different':
				$other = $params[0] ?? '';
				return $value !== $this->getValue( $other );
			case 'confirmed':
				return $value === $this->getValue( $attribute . '_confirmation' );
			case 'date':
				if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
					return false;
				}
				$t = strtotime( (string) $value );
				return $t !== false;
			case 'date_after':
				$date = $params[0] ?? '';
				return $this->dateCompare( $value, $date, 'after' );
			case 'date_before':
				$date = $params[0] ?? '';
				return $this->dateCompare( $value, $date, 'before' );
			case 'date_equals':
				$date = $params[0] ?? '';
				return $this->dateCompare( $value, $date, 'equals' );
			case 'date_after_or_equal':
				$date = $params[0] ?? '';
				return $this->dateCompare( $value, $date, 'after_or_equal' );
			case 'date_before_or_equal':
				$date = $params[0] ?? '';
				return $this->dateCompare( $value, $date, 'before_or_equal' );
			case 'json':
				if ( ! is_string( $value ) ) {
					return false;
				}
				json_decode( $value );
				return json_last_error() === JSON_ERROR_NONE;
			case 'required_if':
				$other = $params[0] ?? '';
				$otherVal = $params[1] ?? null;
				if ( $this->getValue( $other ) == $otherVal ) {
					return ! $this->isEmpty( $value );
				}
				return true;
			case 'required_unless':
				$other = $params[0] ?? '';
				$unlessVals = array_slice( $params, 1 );
				if ( ! in_array( $this->getValue( $other ), $unlessVals, true ) ) {
					return ! $this->isEmpty( $value );
				}
				return true;
			case 'required_with':
				$with = $params;
				$present = false;
				foreach ( $with as $k ) {
					if ( ! $this->isEmpty( $this->getValue( $k ) ) ) {
						$present = true;
						break;
					}
				}
				return ! $present || ! $this->isEmpty( $value );
			case 'required_without':
				$without = $params;
				$absent = false;
				foreach ( $without as $k ) {
					if ( $this->isEmpty( $this->getValue( $k ) ) ) {
						$absent = true;
						break;
					}
				}
				return ! $absent || ! $this->isEmpty( $value );
			case 'required_with_all':
				$all = $params;
				$allPresent = true;
				foreach ( $all as $k ) {
					if ( $this->isEmpty( $this->getValue( $k ) ) ) {
						$allPresent = false;
						break;
					}
				}
				return ! $allPresent || ! $this->isEmpty( $value );
			case 'required_without_all':
				$all = $params;
				$anyPresent = false;
				foreach ( $all as $k ) {
					if ( ! $this->isEmpty( $this->getValue( $k ) ) ) {
						$anyPresent = true;
						break;
					}
				}
				return $anyPresent || ! $this->isEmpty( $value );
			case 'present':
				// Field must exist in data (value can be empty).
				return array_key_exists( $attribute, $this->data ) || $this->hasNestedKey( $this->data, explode( '.', $attribute ) );
			default:
				return true;
		}
	}

	private function hasNestedKey( array $data, array $keys ): bool
	{
		$k = array_shift( $keys );
		if ( ! array_key_exists( $k, $data ) ) {
			return false;
		}
		if ( $keys === [] ) {
			return true;
		}
		return is_array( $data[ $k ] ) && $this->hasNestedKey( $data[ $k ], $keys );
	}

	private function flattenData( array $data, string $prefix = '' ): array
	{
		$out = [];
		foreach ( $data as $k => $v ) {
			$key = $prefix ? $prefix . '.' . $k : $k;
			if ( is_array( $v ) && ! $this->isListOrAssoc( $v ) ) {
				$out = array_merge( $out, $this->flattenData( $v, $key ) );
			} else {
				$out[ $key ] = $v;
			}
		}
		return $out;
	}

	private function isListOrAssoc( array $a ): bool
	{
		return array_keys( $a ) !== range( 0, count( $a ) - 1 );
	}

	private function sizeCompare( $value, int $bound, string $op ): bool
	{
		$size = $this->getSize( $value );
		return $op === 'min' ? $size >= $bound : $size <= $bound;
	}

	private function getSize( $value ): int
	{
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		if ( is_string( $value ) ) {
			return mb_strlen( $value );
		}
		if ( is_array( $value ) ) {
			return count( $value );
		}
		return 0;
	}

	private function dateCompare( $value, string $dateStr, string $op ): bool
	{
		$t = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );
		$d = strtotime( $dateStr );
		if ( $t === false || $d === false ) {
			return false;
		}
		switch ( $op ) {
			case 'after':
				return $t > $d;
			case 'before':
				return $t < $d;
			case 'equals':
				return $t === $d;
			case 'after_or_equal':
				return $t >= $d;
			case 'before_or_equal':
				return $t <= $d;
			default:
				return false;
		}
	}

	private function addError( string $attribute, string $rule, array $params, $value ): void
	{
		$message = $this->getMessage( $attribute, $rule, $params, $value );
		if ( ! isset( $this->errors[ $attribute ] ) ) {
			$this->errors[ $attribute ] = [];
		}
		$this->errors[ $attribute ][] = $message;
	}

	private function getMessage( string $attribute, string $rule, array $params, $value ): string
	{
		$attrName = $this->customAttributes[ $attribute ] ?? str_replace( [ '_', '.' ], ' ', $attribute );
		$key = $attribute . '.' . $rule;
		$template = $this->messages[ $key ] ?? $this->messages[ $rule ] ?? ( self::$defaultMessages[ $rule ] ?? ':attribute invalid.' );
		$replace = [
			':attribute' => $attrName,
			':value'     => is_scalar( $value ) ? $value : json_encode( $value ),
			':min'       => $params[0] ?? '',
			':max'       => $params[0] ?? $params[1] ?? '',
			':size'      => $params[0] ?? '',
			':values'    => implode( ', ', $params ),
			':other'     => $this->customAttributes[ $params[0] ?? '' ] ?? str_replace( [ '_', '.' ], ' ', (string) ( $params[0] ?? '' ) ),
			':date'      => $params[0] ?? '',
		];
		return str_replace( array_keys( $replace ), array_values( $replace ), $template );
	}
}
