<?php

namespace Plugifity\Contract\Interface;

/**
 * Migration Interface
 *
 * Contract for database migrations (Laravel-style).
 * Implement this interface for classes that define reversible database schema changes.
 *
 * @see https://laravel.com/docs/migrations
 */
interface MigrationInterface
{
    /**
     * Run the migrations.
     *
     * Apply schema changes: create/alter tables, add columns, indexes, etc.
     *
     * @return void
     */
    public function up(): void;

    /**
     * Reverse the migrations.
     *
     * Undo what was done in up(): drop tables, remove columns, etc.
     *
     * @return void
     */
    public function down(): void;
}
