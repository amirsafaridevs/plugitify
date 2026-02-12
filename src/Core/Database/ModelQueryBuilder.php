<?php

namespace Plugifity\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractModel;

/**
 * Model query builder (Laravel-style).
 * Wraps QueryBuilder and hydrates results to model instances.
 *
 * @template T of AbstractModel
 */
class ModelQueryBuilder
{
    /**
     * @var class-string<T>
     */
    protected string $modelClass;

    protected QueryBuilder $query;

    /**
     * @param class-string<T> $modelClass
     */
    public function __construct( string $modelClass )
    {
        $this->modelClass = $modelClass;
        $this->query      = $modelClass::newQuery();
    }

    /**
     * @return $this
     */
    public function where( string $column, $operatorOrValue, $value = null ): self
    {
        $this->query->where( $column, $operatorOrValue, $value );
        return $this;
    }

    /**
     * @return $this
     */
    public function whereIn( string $column, array $values ): self
    {
        $this->query->whereIn( $column, $values );
        return $this;
    }

    /**
     * @return $this
     */
    public function whereNull( string $column ): self
    {
        $this->query->whereNull( $column );
        return $this;
    }

    /**
     * @return $this
     */
    public function whereNotNull( string $column ): self
    {
        $this->query->whereNotNull( $column );
        return $this;
    }

    /**
     * @return $this
     */
    public function orderBy( string $column, string $direction = 'ASC' ): self
    {
        $this->query->orderBy( $column, $direction );
        return $this;
    }

    /**
     * @return $this
     */
    public function limit( int $limit ): self
    {
        $this->query->limit( $limit );
        return $this;
    }

    /**
     * @return $this
     */
    public function offset( int $offset ): self
    {
        $this->query->offset( $offset );
        return $this;
    }

    /**
     * Select columns.
     *
     * @param string|array<int, string> $columns
     * @return $this
     */
    public function select( $columns = [ '*' ] ): self
    {
        $this->query->select( $columns );
        return $this;
    }

    /**
     * Hydrate a single row to model.
     *
     * @return T
     */
    protected function hydrateRow( object $row ): AbstractModel
    {
        $model = $this->modelClass::fromRow( $row );
        $model->exists = true;
        return $model;
    }

    /**
     * Execute query and return models.
     *
     * @return array<int, T>
     */
    public function get(): array
    {
        $rows  = $this->query->get();
        $items = [];
        foreach ( $rows as $row ) {
            $items[] = $this->hydrateRow( $row );
        }
        return $items;
    }

    /**
     * Get first model or null.
     *
     * @return T|null
     */
    public function first(): ?AbstractModel
    {
        $rows = $this->query->limit( 1 )->get();
        $row  = $rows[0] ?? null;
        return $row !== null ? $this->hydrateRow( $row ) : null;
    }

    /**
     * Find by primary key.
     *
     * @param int|string $id
     * @return T|null
     */
    public function find( $id ): ?AbstractModel
    {
        $instance = new $this->modelClass();
        $keyName  = $instance->getKeyName();
        return $this->where( $keyName, $id )->first();
    }

    /**
     * Find by primary key or throw.
     *
     * @param int|string $id
     * @return T
     * @throws \RuntimeException
     */
    public function findOrFail( $id ): AbstractModel
    {
        $model = $this->find( $id );
        if ( $model === null ) {
            throw new \RuntimeException( 'Model not found: ' . $this->modelClass . ' with key ' . $id );
        }
        return $model;
    }

    /**
     * Paginate results.
     *
     * @param int $perPage
     * @param int $page    1-based page number
     * @return Paginator<T>
     */
    public function paginate( int $perPage = 15, int $page = 1 ): Paginator
    {
        $page   = max( 1, $page );
        $total  = $this->query->count();
        $offset = ( $page - 1 ) * $perPage;
        $items  = $this->query->limit( $perPage )->offset( $offset )->get();
        $models = [];
        foreach ( $items as $row ) {
            $models[] = $this->hydrateRow( $row );
        }
        return new Paginator( $models, $total, $page, $perPage );
    }

    /**
     * Count rows.
     */
    public function count( string $column = '*' ): int
    {
        return $this->query->count( $column );
    }

    /**
     * Check if any row exists.
     */
    public function exists(): bool
    {
        return $this->query->exists();
    }

    /**
     * Insert row(s). For single row, timestamps are applied via model's prepareCreate.
     *
     * @param array<string, mixed>|array<int, array<string, mixed>> $data Single row or list of rows
     * @return int|false Last insert ID for single row, number of rows for bulk, false on failure
     */
    public function insert( array $data )
    {
        $isBulk = isset( $data[0] ) && is_array( $data[0] );
        if ( $isBulk ) {
            $prepared = [];
            foreach ( $data as $row ) {
                $prepared[] = $this->modelClass::prepareCreateForQuery( $row );
            }
            return $this->query->insert( $prepared );
        }
        $prepared = $this->modelClass::prepareCreateForQuery( $data );
        return $this->query->insert( $prepared );
    }

    /**
     * Update rows matching current where. Applies model's prepareUpdate (e.g. updated_at).
     *
     * @param array<string, mixed> $data
     * @return int|false Rows affected, false on failure
     */
    public function update( array $data )
    {
        $prepared = $this->modelClass::prepareUpdateForQuery( $data );
        return $this->query->update( $prepared );
    }

    /**
     * Delete rows matching current where.
     *
     * @return int|false Rows affected, false on failure
     */
    public function delete()
    {
        return $this->query->delete();
    }

    /**
     * Get underlying QueryBuilder (for advanced use).
     *
     * @return QueryBuilder
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }
}
