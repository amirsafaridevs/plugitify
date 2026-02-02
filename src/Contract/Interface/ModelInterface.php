<?php

namespace Plugifity\Contract\Interface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model Interface
 *
 * Contract for entity models (table row → object).
 */
interface ModelInterface
{
    /**
     * Table name (without prefix).
     */
    public static function getTable(): string;

    /**
     * Create model instance from DB row.
     */
    public static function fromRow( object $row ): static;

    /**
     * Convert model to array (for insert/update).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
