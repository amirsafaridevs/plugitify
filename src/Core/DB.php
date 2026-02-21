<?php

namespace Plugifity\Core;

use Plugifity\Core\Database\QueryBuilder;
use Plugifity\Core\Database\SchemaBuilder;

/**
 * DB
 *
 * Laravel-style database facade for WordPress.
 * Uses global $wpdb (WordPress standard): prefix, charset/collate, and connection
 * from wp-config / WordPress environment. Use for migrations (Schema) and queries (table).
 */
class DB
{
    /**
     * WordPress database instance (standard WordPress connection)
     *
     * @var \wpdb|null
     */
    protected static ?\wpdb $wpdb = null;

    /**
     * Schema builder instance
     *
     * @var SchemaBuilder|null
     */
    protected static ?SchemaBuilder $schemaBuilder = null;

    /**
     * Get WordPress database instance.
     * Uses global $wpdb (WordPress standard for plugins).
     *
     * @return \wpdb
     */
    public static function connection(): \wpdb
    {
        if (static::$wpdb === null) {
            global $wpdb;
            if (!$wpdb instanceof \wpdb) {
                throw new \RuntimeException('WordPress $wpdb is not available. Ensure WordPress is loaded.');
            }
            static::$wpdb = $wpdb;
        }
        return static::$wpdb;
    }

    /**
     * Set the wpdb instance (e.g. for testing or custom connection).
     *
     * @param \wpdb $wpdb
     * @return void
     */
    public static function setConnection(\wpdb $wpdb): void
    {
        static::$wpdb = $wpdb;
        static::$schemaBuilder = null;
    }

    /**
     * Get table prefix (from WordPress wp_config / $wpdb->prefix).
     *
     * @return string
     */
    public static function getPrefix(): string
    {
        return static::connection()->prefix;
    }

    /**
     * Alias for getPrefix().
     *
     * @return string
     */
    public static function prefix(): string
    {
        return static::getPrefix();
    }

    /**
     * Get schema builder for migrations (create/drop tables, alter).
     *
     * @return SchemaBuilder
     */
    public static function schema(): SchemaBuilder
    {
        if (static::$schemaBuilder === null) {
            static::$schemaBuilder = new SchemaBuilder(static::connection());
        }
        return static::$schemaBuilder;
    }

    /**
     * Start a fluent query on a table.
     * Table name can be with or without prefix; prefix is applied if missing.
     *
     * @param string $table
     * @return QueryBuilder
     */
    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder(static::connection(), $table);
    }

    /**
     * Run a raw query (uses wpdb->query). Prefer prepared statements for user input.
     *
     * @param string $sql
     * @return int|false Rows affected or false
     */
    public static function statement(string $sql)
    {
        return static::connection()->query($sql);
    }

    /**
     * Run raw SELECT and get results.
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     * @return array<int, object>
     */
    public static function select(string $sql, array $bindings = []): array
    {
        $wpdb = static::connection();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared with bindings or safe
        $prepared = $bindings !== [] ? $wpdb->prepare($sql, ...$bindings) : $sql;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above or safe SQL
        $results = $wpdb->get_results($prepared);
        return $results !== null ? $results : [];
    }

    /**
     * Run raw SELECT and get single row.
     *
     * @param string $sql
     * @param array<int, mixed> $bindings
     * @return object|null
     */
    public static function selectOne(string $sql, array $bindings = []): ?object
    {
        $wpdb = static::connection();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared with bindings or safe
        $prepared = $bindings !== [] ? $wpdb->prepare($sql, ...$bindings) : $sql;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above or safe SQL
        $row = $wpdb->get_row($prepared);
        return $row ?: null;
    }

    /**
     * Insert into table (direct wpdb->insert).
     *
     * @param string $table
     * @param array<string, mixed> $data
     * @return int|false Insert ID or false
     */
    public static function insert(string $table, array $data)
    {
        $wpdb = static::connection();
        $fullTable = (strpos($table, $wpdb->prefix) === 0) ? $table : $wpdb->prefix . $table;
        $result = $wpdb->insert($fullTable, $data);
        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Update table (direct wpdb->update).
     * When $format / $where_format are null, defaults are applied so the query is always properly prepared.
     *
     * @param string $table Table name with or without WordPress prefix (e.g. plugitify_chats or wp_plugitify_chats).
     * @param array<string, mixed> $data Column => value to set.
     * @param array<string, mixed> $where Column => value for WHERE.
     * @param array<int, string>|null $format Format for $data (e.g. ['%s', '%d']). Default: all '%s'.
     * @param array<int, string>|null $where_format Format for $where (e.g. ['%d']). Default: all '%s'.
     * @return int|false Rows affected or false on failure
     */
    public static function update(string $table, array $data, array $where, ?array $format = null, ?array $where_format = null)
    {
        $wpdb = static::connection();
        $fullTable = (strpos($table, $wpdb->prefix) === 0) ? $table : $wpdb->prefix . $table;
        if ($format === null) {
            $format = array_fill(0, count($data), '%s');
        }
        if ($where_format === null) {
            $where_format = array_fill(0, count($where), '%s');
        }
        return $wpdb->update($fullTable, $data, $where, $format, $where_format);
    }

    /**
     * Delete from table (direct wpdb->delete).
     *
     * @param string $table
     * @param array<string, mixed> $where
     * @return int|false Rows affected or false
     */
    public static function delete(string $table, array $where)
    {
        $wpdb = static::connection();
        $fullTable = (strpos($table, $wpdb->prefix) === 0) ? $table : $wpdb->prefix . $table;
        return $wpdb->delete($fullTable, $where);
    }

    /**
     * Get charset and collate (for manual CREATE TABLE / dbDelta).
     *
     * @return string
     */
    public static function getCharsetCollate(): string
    {
        return static::connection()->get_charset_collate();
    }

    /**
     * Escape value for SQL (uses wpdb->_escape).
     *
     * @param string $value
     * @return string
     */
    public static function escape(string $value): string
    {
        return static::connection()->_escape($value);
    }

    /**
     * Prepare a query (uses wpdb->prepare).
     *
     * @param string $query
     * @param mixed ...$args
     * @return string
     */
    public static function prepare(string $query, ...$args): string
    {
        return static::connection()->prepare($query, ...$args);
    }

    /**
     * Get last insert ID.
     *
     * @return int
     */
    public static function lastInsertId(): int
    {
        return (int) static::connection()->insert_id;
    }

    /**
     * Get last error from wpdb.
     *
     * @return string
     */
    public static function lastError(): string
    {
        return static::connection()->last_error;
    }

    /**
     * Start a transaction (uses wpdb; WordPress supports transactions on compatible MySQL).
     *
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        return static::connection()->query('START TRANSACTION') !== false;
    }

    /**
     * Commit transaction.
     *
     * @return bool
     */
    public static function commit(): bool
    {
        return static::connection()->query('COMMIT') !== false;
    }

    /**
     * Rollback transaction.
     *
     * @return bool
     */
    public static function rollBack(): bool
    {
        return static::connection()->query('ROLLBACK') !== false;
    }
}
