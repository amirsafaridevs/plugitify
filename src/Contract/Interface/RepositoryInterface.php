<?php

namespace Plugifity\Contract\Interface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repository Interface
 *
 * Contract for data access (find, get, create, update, delete).
 */
interface RepositoryInterface
{
    /**
     * Find entity by id.
     */
    public function find( int $id ): ?object;

    /**
     * Get list of entities (signature may be extended by implementations).
     *
     * @return array<int, object>
     */
    public function get(): array;

    /**
     * Create a new record.
     *
     * @param array<string, mixed> $data
     * @return int|false Insert ID or false
     */
    public function create( array $data );

    /**
     * Update record by id.
     *
     * @param array<string, mixed> $data
     * @return int|false Rows affected or false
     */
    public function update( int $id, array $data );

    /**
     * Delete record by id.
     *
     * @return int|false Rows affected or false
     */
    public function delete( int $id );
}
