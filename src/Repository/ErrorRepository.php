<?php

namespace Plugifity\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Model\Error;

/**
 * Repository for plugifity_errors.
 */
class ErrorRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return Error::class;
    }

    /**
     * Get all errors (optionally by code or level).
     *
     * @return Error[]
     */
    public function get( ?string $code = null, ?string $level = null ): array
    {
        $query = $this->newQuery()->orderBy( $this->getOrderColumn(), $this->getOrderDirection() );
        if ( $code !== null ) {
            $query->where( 'code', $code );
        }
        if ( $level !== null ) {
            $query->where( 'level', $level );
        }
        $rows = $query->get();
        $result = [];
        foreach ( $rows as $row ) {
            $result[] = Error::fromRow( $row );
        }
        return $result;
    }

    /**
     * Get paginated errors (newest first).
     *
     * @param int $limit
     * @param int $offset
     * @param string|null $level Filter by level (error, warning, critical, etc.)
     * @return Error[]
     */
    public function getPaginated( int $limit, int $offset, ?string $level = null ): array
    {
        $query = $this->newQuery()->orderBy( 'created_at', 'DESC' )->limit( $limit )->offset( $offset );
        if ( $level !== null ) {
            $query->where( 'level', $level );
        }
        $rows = $query->get();
        $result = [];
        foreach ( $rows as $row ) {
            $result[] = Error::fromRow( $row );
        }
        return $result;
    }

    /**
     * Count total errors (optionally by level).
     *
     * @param string|null $level
     * @return int
     */
    public function countAll( ?string $level = null ): int
    {
        $query = $this->newQuery();
        if ( $level !== null ) {
            $query->where( 'level', $level );
        }
        return $query->count();
    }

    /**
     * Delete all error logs.
     *
     * @return int Number of rows deleted
     */
    public function deleteAll(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . $this->getTable();
        $result = $wpdb->query( "DELETE FROM `{$table}`" );
        return $result !== false ? (int) $result : 0;
    }
}
