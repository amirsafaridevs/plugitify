<?php

namespace Plugifity\Contract\Abstract;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Interface\ModelInterface;

/**
 * Abstract Model
 *
 * Base for entity models. Subclasses must define TABLE and implement fromRow() and toArray().
 */
abstract class AbstractModel implements ModelInterface
{
    /**
     * Table name (without prefix). Must be defined in subclass.
     */
    public const TABLE = '';

    public static function getTable(): string
    {
        return static::TABLE;
    }

    /**
     * Create model instance from DB row. Must be implemented in subclass.
     */
    abstract public static function fromRow( object $row ): static;

    /**
     * Convert model to array for insert/update. Must be implemented in subclass.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
