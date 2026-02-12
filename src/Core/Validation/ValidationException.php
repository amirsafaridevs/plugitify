<?php

namespace Plugifity\Core\Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thrown when validation fails (e.g. Validator::validate()).
 */
class ValidationException extends \RuntimeException
{
	private Validator $validator;

	public function __construct( Validator $validator, string $message = '', int $code = 0, ?\Throwable $previous = null )
	{
		$this->validator = $validator;
		$message = $message !== '' ? $message : $validator->errors()->first();
		parent::__construct( $message, $code, $previous );
	}

	public function getValidator(): Validator
	{
		return $this->validator;
	}

	/**
	 * Get validation errors.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function errors(): array
	{
		return $this->validator->getErrors();
	}

	public function getMessageBag(): MessageBag
	{
		return $this->validator->errors();
	}
}
