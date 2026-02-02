<?php

namespace Plugifity\Contract\Abstract;

use Plugifity\Contract\Interface\MigrationInterface;

/**
 * Abstract Migration
 *
 * Base class for database migrations (Laravel-style).
 * Extend this class and implement up() and down() for each migration.
 *
 * @see https://laravel.com/docs/migrations
 */
abstract class AbstractMigration implements MigrationInterface
{
    /**
     * The database connection that should be used by the migration.
     * Null means use the default connection (Laravel-style).
     *
     * @var string|null
     */
    protected ?string $connection = null;

    /**
     * Run the migrations.
     *
     * @return void
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    abstract public function down(): void;

    /**
     * Determine if this migration should run.
     * Override to skip migration conditionally (e.g. feature flags).
     *
     * @return bool
     */
    public function shouldRun(): bool
    {
        return true;
    }

    /**
     * Get the database connection name for this migration.
     *
     * @return string|null
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * Set the database connection name for this migration.
     *
     * @param string|null $connection
     * @return self
     */
    public function setConnection(?string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }
}
