<?php

namespace Plugifity\Core\Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bag of validation error messages (Laravel-style).
 */
class MessageBag
{
	/** @var array<string, array<int, string>> */
	private array $messages;

	/**
	 * @param array<string, array<int, string>> $messages
	 */
	public function __construct( array $messages = [] )
	{
		$this->messages = $messages;
	}

	/**
	 * Get first message for attribute, or first of all.
	 *
	 * @param string|null $key Attribute key or null for first overall.
	 * @return string
	 */
	public function first( ?string $key = null ): string
	{
		if ( $key !== null ) {
			$list = $this->messages[ $key ] ?? [];
			return $list[0] ?? '';
		}
		foreach ( $this->messages as $list ) {
			if ( isset( $list[0] ) ) {
				return $list[0];
			}
		}
		return '';
	}

	/**
	 * Get all messages for an attribute.
	 *
	 * @param string $key
	 * @return array<int, string>
	 */
	public function get( string $key ): array
	{
		return $this->messages[ $key ] ?? [];
	}

	/**
	 * Get all messages as flat array (one per attribute, first message).
	 *
	 * @return array<int, string>
	 */
	public function all(): array
	{
		$out = [];
		foreach ( $this->messages as $list ) {
			foreach ( $list as $msg ) {
				$out[] = $msg;
			}
		}
		return $out;
	}

	/**
	 * Get messages grouped by attribute.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function toArray(): array
	{
		return $this->messages;
	}

	/**
	 * Whether the bag has any messages.
	 */
	public function isNotEmpty(): bool
	{
		return $this->messages !== [];
	}

	/**
	 * Whether the bag has no messages.
	 */
	public function isEmpty(): bool
	{
		return $this->messages === [];
	}

	/**
	 * Check if attribute has errors.
	 */
	public function has( string $key ): bool
	{
		return isset( $this->messages[ $key ] ) && $this->messages[ $key ] !== [];
	}

	/**
	 * Get count of attributes with errors.
	 */
	public function count(): int
	{
		return count( $this->messages );
	}
}
