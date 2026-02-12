<?php

namespace Plugifity\Contract\Abstract;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Interface\ModelInterface;
use Plugifity\Contract\Interface\RepositoryInterface;
use Plugifity\Core\DB;
use Plugifity\Core\Database\QueryBuilder;
use Plugifity\Core\Database\RepositoryQueryBuilder;

/**
 * Abstract Repository (Laravel-style)
 *
 * Base for data access. Subclasses must define getModelClass(); may override newQuery(), get(), prepareCreate(), prepareUpdate(), delete().
 * Supports query(), where(), whereIn(), whereNull(), whereNotNull(), get(), first(), find(), findOrFail(), paginate(), count(), exists(), insert(), update(), delete().
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
     * Expose base query for RepositoryQueryBuilder (fresh instance each time).
     */
    public function getBaseQuery(): QueryBuilder
    {
        return $this->newQuery();
    }

    /**
     * Expose model class for hydration in RepositoryQueryBuilder.
     *
     * @return class-string<ModelInterface>
     */
    public function getModelClassForQuery(): string
    {
        return $this->getModelClass();
    }

    /**
     * Prepare data for insert (for use by RepositoryQueryBuilder).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function prepareCreateForQuery( array $data ): array
    {
        return $this->prepareCreate( $data );
    }

    /**
     * Prepare data for update (for use by RepositoryQueryBuilder).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function prepareUpdateForQuery( array $data ): array
    {
        return $this->prepareUpdate( $data );
    }

    /**
     * New repository query builder (Laravel-style). Uses newQuery() so default scopes apply.
     *
     * @return RepositoryQueryBuilder<ModelInterface>
     */
    public function query(): RepositoryQueryBuilder
    {
        return new RepositoryQueryBuilder( $this );
    }

    /**
     * Start a query with where.
     *
     * @param string     $column
     * @param mixed      $operatorOrValue
     * @param mixed|null $value
     * @return RepositoryQueryBuilder<ModelInterface>
     */
    public function where( string $column, $operatorOrValue, $value = null ): RepositoryQueryBuilder
    {
        return $this->query()->where( $column, $operatorOrValue, $value );
    }

    /**
     * Start a query with whereIn.
     *
     * @param string        $column
     * @param array<int, mixed> $values
     * @return RepositoryQueryBuilder<ModelInterface>
     */
    public function whereIn( string $column, array $values ): RepositoryQueryBuilder
    {
        return $this->query()->whereIn( $column, $values );
    }

    /**
     * Start a query with whereNull.
     *
     * @param string $column
     * @return RepositoryQueryBuilder<ModelInterface>
     */
    public function whereNull( string $column ): RepositoryQueryBuilder
    {
        return $this->query()->whereNull( $column );
    }

    /**
     * Start a query with whereNotNull.
     *
     * @param string $column
     * @return RepositoryQueryBuilder<ModelInterface>
     */
    public function whereNotNull( string $column ): RepositoryQueryBuilder
    {
        return $this->query()->whereNotNull( $column );
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
        return $this->query()->find( $id );
    }

    /**
     * Find by id or throw.
     *
     * @param int $id
     * @return object
     * @throws \RuntimeException
     */
    public function findOrFail( int $id ): object
    {
        return $this->query()->findOrFail( $id );
    }

    /**
     * @return array<int, object>
     */
    public function get(): array
    {
        return $this->query()
            ->orderBy( $this->getOrderColumn(), $this->getOrderDirection() )
            ->get();
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
