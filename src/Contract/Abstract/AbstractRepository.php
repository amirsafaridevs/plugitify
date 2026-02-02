<?php

namespace Plugifity\Contract\Abstract;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Interface\ModelInterface;
use Plugifity\Contract\Interface\RepositoryInterface;
use Plugifity\Core\DB;
use Plugifity\Core\Database\QueryBuilder;

/**
 * Abstract Repository
 *
 * Base for data access. Subclasses must define getModelClass(); may override newQuery(), get(), prepareCreate(), prepareUpdate(), delete().
 */
abstract class AbstractRepository implements RepositoryInterface
{
    /**
     * Model class name (e.g. Plugifity\Model\Chat::class). Must be defined in subclass.
     *
     * @return class-string<ModelInterface>
     */
    abstract protected function getModelClass(): string;

    protected function getTable(): string
    {
        $modelClass = $this->getModelClass();
        return $modelClass::getTable();
    }

    /**
     * Base query. Override to add default scopes (e.g. soft delete).
     */
    protected function newQuery(): QueryBuilder
    {
        return DB::table( $this->getTable() );
    }

    /**
     * Default order column for get().
     */
    protected function getOrderColumn(): string
    {
        return 'created_at';
    }

    /**
     * Default order direction for get().
     */
    protected function getOrderDirection(): string
    {
        return 'DESC';
    }

    /**
     * Add timestamps (and other defaults) before insert. Override in subclass if needed.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareCreate( array $data ): array
    {
        $now = current_time( 'mysql' );
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $data['updated_at'] ?? $now;
        return $data;
    }

    /**
     * Add updated_at before update. Override in subclass if needed.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareUpdate( array $data ): array
    {
        $data['updated_at'] = $data['updated_at'] ?? current_time( 'mysql' );
        return $data;
    }

    public function find( int $id ): ?object
    {
        $row = $this->newQuery()->where( 'id', $id )->first();
        if ( $row === null ) {
            return null;
        }
        $modelClass = $this->getModelClass();
        return $modelClass::fromRow( $row );
    }

    /**
     * @return array<int, object>
     */
    public function get(): array
    {
        $rows = $this->newQuery()
            ->orderBy( $this->getOrderColumn(), $this->getOrderDirection() )
            ->get();
        $modelClass = $this->getModelClass();
        $result = [];
        foreach ( $rows as $row ) {
            $result[] = $modelClass::fromRow( $row );
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function create( array $data )
    {
        $data = $this->prepareCreate( $data );
        return DB::table( $this->getTable() )->insert( $data );
    }

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function update( int $id, array $data )
    {
        $data = $this->prepareUpdate( $data );
        return DB::table( $this->getTable() )->where( 'id', $id )->update( $data );
    }

    /**
     * @return int|false
     */
    public function delete( int $id )
    {
        return DB::table( $this->getTable() )->where( 'id', $id )->delete();
    }
}
