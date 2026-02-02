<?php

namespace Plugifity\Service\Admin\AgentTools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Core\DB;
use Plugifity\Service\Admin\Settings;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/**
 * Agent tool: run SQL on the database.
 * SELECT runs without permission. INSERT/UPDATE/DELETE (and other writes) require
 * the admin to enable "Allow database writes" in Settings first.
 */
class DatabaseQueryTool extends Tool
{
    /** @var string[] SQL keywords that modify data (require admin permission). */
    private const WRITE_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'TRUNCATE',
        'CREATE', 'DROP', 'ALTER', 'RENAME',
    ];

    public function __construct()
    {
        parent::__construct(
            'database_query',
            'Run a single SQL statement on the WordPress database. Use full table names with the site prefix (e.g. wp_posts, wp_users). SELECT runs without permission. For INSERT, UPDATE, DELETE or other data/schema changes, the admin must first enable "Allow database writes" in Assistant Settings. Only run one statement at a time. Return results for SELECT; for writes return rows affected or success.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'sql',
                type: PropertyType::STRING,
                description: 'A single SQL statement (e.g. SELECT * FROM wp_posts LIMIT 10, or INSERT INTO ...). Use WordPress table prefix in table names. For writes, admin must enable "Allow database writes" in Settings.',
                required: true
            ),
        ];
    }

    /**
     * @param string $sql Single SQL statement.
     * @return string JSON: rows for SELECT, or { success, rows_affected } for writes, or { error } on failure.
     */
    public function __invoke( string $sql ): string
    {
        $sql = $this->normalizeSql( $sql );
        if ( $sql === '' ) {
            return wp_json_encode( [ 'error' => 'Empty or invalid SQL.' ] );
        }

        $keyword = $this->getFirstKeyword( $sql );
        $isWrite = in_array( $keyword, self::WRITE_KEYWORDS, true );

        if ( $isWrite ) {
            $allowed = (bool) get_option( Settings::OPTION_ALLOW_DB_WRITES, false );
            if ( ! $allowed ) {
                return wp_json_encode( [
                    'error' => 'Database writes are disabled. Ask the admin to enable "Allow database writes" in Assistant Settings, then try again.',
                ] );
            }

            try {
                $result = DB::statement( $sql );
                if ( $result === false ) {
                    $err = DB::lastError();
                    return wp_json_encode( [ 'error' => 'Query failed: ' . ( $err ?: 'Unknown error' ) ] );
                }
                return wp_json_encode( [
                    'success'        => true,
                    'rows_affected'   => (int) $result,
                ] );
            } catch ( \Throwable $e ) {
                return wp_json_encode( [ 'error' => 'Query failed: ' . $e->getMessage() ] );
            }
        }

        if ( $keyword !== 'SELECT' ) {
            return wp_json_encode( [
                'error' => 'Unsupported statement type. Use SELECT for reads; for INSERT/UPDATE/DELETE enable "Allow database writes" in Settings.',
            ] );
        }

        try {
            $rows = DB::select( $sql );
            $data = [];
            foreach ( $rows as $row ) {
                $data[] = (array) $row;
            }
            return wp_json_encode( $data );
        } catch ( \Throwable $e ) {
            return wp_json_encode( [ 'error' => 'Query failed: ' . $e->getMessage() ] );
        }
    }

    private function normalizeSql( string $sql ): string
    {
        $sql = trim( $sql );
        // Only first statement (no multiple statements).
        $pos = strpos( $sql, ';' );
        if ( $pos !== false ) {
            $sql = trim( substr( $sql, 0, $pos ) );
        }
        return $sql;
    }

    private function getFirstKeyword( string $sql ): string
    {
        $sql = trim( $sql );
        if ( $sql === '' ) {
            return '';
        }
        $first = preg_split( '/\s+/', $sql, 2, PREG_SPLIT_NO_EMPTY );
        return isset( $first[0] ) ? strtoupper( $first[0] ) : '';
    }
}
