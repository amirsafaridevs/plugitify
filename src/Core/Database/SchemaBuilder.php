<?php

namespace Plugifity\Core\Database;

use Closure;

/**
 * SchemaBuilder
 *
 * Laravel-style schema builder for migrations.
 * Uses WordPress $wpdb and dbDelta for table create/update (standard for plugins).
 */
class SchemaBuilder
{
    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    protected \wpdb $wpdb;

    /**
     * Table prefix (from wpdb)
     *
     * @var string
     */
    protected string $prefix;

    /**
     * @param \wpdb $wpdb
     */
    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix;
    }

    /**
     * Create a table.
     *
     * @param string $table Table name (with or without prefix; if without, prefix is applied)
     * @param Closure $callback Receives Blueprint, e.g. function (Blueprint $table) { $table->id(); ... }
     * @return bool True on success
     */
    public function create(string $table, Closure $callback): bool
    {
        $fullName = $this->getTableName($table);
        $blueprint = new Blueprint($fullName);
        $callback($blueprint);

        $charsetCollate = $this->wpdb->get_charset_collate();
        $sql = $blueprint->toSql($charsetCollate);

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        dbDelta($sql);

        return true;
    }

    /**
     * Create a table only if it does not exist (dbDelta handles this when using CREATE TABLE in schema).
     * Alias for create() since dbDelta is additive; for explicit IF NOT EXISTS use raw query in migration.
     *
     * @param string $table
     * @param Closure $callback
     * @return bool
     */
    public function createIfNotExists(string $table, Closure $callback): bool
    {
        if ($this->hasTable($table)) {
            return true;
        }
        return $this->create($table, $callback);
    }

    /**
     * Drop a table.
     *
     * @param string $table
     * @return bool
     */
    public function drop(string $table): bool
    {
        $fullName = $this->getTableName($table);
        $sql = 'DROP TABLE IF EXISTS `' . esc_sql($fullName) . '`';
        return $this->wpdb->query($sql) !== false;
    }

    /**
     * Drop table if it exists.
     *
     * @param string $table
     * @return bool
     */
    public function dropIfExists(string $table): bool
    {
        return $this->drop($table);
    }

    /**
     * Modify an existing table (ALTER TABLE).
     *
     * @param string $table
     * @param Closure $callback Receives Blueprint; use to add/drop columns (add column only via Blueprint; drop via dropColumn).
     * @return bool
     */
    public function table(string $table, Closure $callback): bool
    {
        $fullName = $this->getTableName($table);
        $blueprint = new Blueprint($fullName);
        $callback($blueprint);

        $columns = $blueprint->getColumns();
        foreach ($columns as $name => $def) {
            if ($this->hasColumn($table, $name)) {
                continue;
            }
            $type = $def['type'];
            $unsigned = !empty($def['unsigned']) ? ' unsigned' : '';
            $null = !empty($def['nullable']) ? ' NULL' : ' NOT NULL';
            $default = '';
            if (array_key_exists('default', $def)) {
                $d = $def['default'];
                $default = $d === 'CURRENT_TIMESTAMP' ? ' DEFAULT CURRENT_TIMESTAMP' : " DEFAULT '" . addslashes((string) $d) . "'";
            }
            $sql = "ALTER TABLE `{$fullName}` ADD COLUMN `{$name}` {$type}{$unsigned}{$null}{$default}";
            if ($this->wpdb->query($sql) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Drop a column.
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function dropColumn(string $table, string $column): bool
    {
        $fullName = $this->getTableName($table);
        $sql = "ALTER TABLE `{$fullName}` DROP COLUMN `" . esc_sql($column) . "`";
        return $this->wpdb->query($sql) !== false;
    }

    /**
     * Rename a table.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function rename(string $from, string $to): bool
    {
        $fromFull = $this->getTableName($from);
        $toFull = $this->getTableName($to);
        $sql = "RENAME TABLE `{$fromFull}` TO `{$toFull}`";
        return $this->wpdb->query($sql) !== false;
    }

    /**
     * Check if a table exists.
     *
     * @param string $table
     * @return bool
     */
    public function hasTable(string $table): bool
    {
        $fullName = $this->getTableName($table);
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
                DB_NAME,
                $fullName
            )
        );
        return (int) $result > 0;
    }

    /**
     * Check if a column exists on a table.
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function hasColumn(string $table, string $column): bool
    {
        $fullName = $this->getTableName($table);
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = %s AND table_name = %s AND column_name = %s',
                DB_NAME,
                $fullName,
                $column
            )
        );
        return (int) $result > 0;
    }

    /**
     * Get full table name (with prefix if not already prefixed).
     *
     * @param string $table
     * @return string
     */
    public function getTableName(string $table): string
    {
        if ($this->prefix !== '' && strpos($table, $this->prefix) !== 0) {
            return $this->prefix . $table;
        }
        return $table;
    }

    /**
     * Get charset collate string from WordPress.
     *
     * @return string
     */
    public function getCharsetCollate(): string
    {
        return $this->wpdb->get_charset_collate();
    }

    /**
     * Get wpdb instance.
     *
     * @return \wpdb
     */
    public function getWpdb(): \wpdb
    {
        return $this->wpdb;
    }
}
