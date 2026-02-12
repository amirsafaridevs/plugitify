<?php

namespace Plugifity\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Contract\Interface\ModelInterface;

/**
 * Repository query builder (Laravel-style).
 * Uses repository's base query (with default scopes) and prepareCreate/prepareUpdate.
 *
 * @template T of ModelInterface
 */
class RepositoryQueryBuilder
{
    protected AbstractRepository $repository;

    protected QueryBuilder $query;

    /**
     * @var class-string<T>
     */
    protected string $modelClass;

    public function __construct( AbstractRepository $repository )
    {
        $this->repository = $repository;
        $this->query      = $repository->getBaseQuery();
        $this->modelClass = $repository->getModelClassForQuery();
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
     * @param string|array<int, string> $columns
     * @return $this
     */
    public function select( $columns = [ '*' ] ): self
    {
        $this->query->select( $columns );
        return $this;
    }

    /**
     * @return T
     */
    protected function hydrateRow( object $row ): ModelInterface
    {
        $model = $this->modelClass::fromRow( $row );
        if ( method_exists( $model, 'exists' ) ) {
            $model->exists = true;
        }
        return $model;
    }

    /**
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
     * @return T|null
     */
    public function first(): ?ModelInterface
    {
        $rows = $this->query->limit( 1 )->get();
        $row  = $rows[0] ?? null;
        return $row !== null ? $this->hydrateRow( $row ) : null;
    }

    /**
     * @param int|string $id
     * @return T|null
     */
    public function find( $id ): ?ModelInterface
    {
        $instance = new $this->modelClass();
        $keyName  = method_exists( $instance, 'getKeyName' ) ? $instance->getKeyName() : 'id';
        return $this->where( $keyName, $id )->first();
    }

    /**
     * @param int|string $id
     * @return T
     * @throws \RuntimeException
     */
    public function findOrFail( $id ): ModelInterface
    {
        $model = $this->find( $id );
        if ( $model === null ) {
            throw new \RuntimeException( 'Model not found: ' . $this->modelClass . ' with key ' . $id );
        }
        return $model;
    }

    /**
     * @param int $perPage
     * @param int $page
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

    public function count( string $column = '*' ): int
    {
        return $this->query->count( $column );
    }

    public function exists(): bool
    {
        return $this->query->exists();
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $data
     * @return int|false
     */
    public function insert( array $data )
    {
        $isBulk = isset( $data[0] ) && is_array( $data[0] );
        if ( $isBulk ) {
            $prepared = [];
            foreach ( $data as $row ) {
                $prepared[] = $this->repository->prepareCreateForQuery( $row );
            }
            return $this->query->insert( $prepared );
        }
        $prepared = $this->repository->prepareCreateForQuery( $data );
        return $this->query->insert( $prepared );
    }

    /**
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function update( array $data )
    {
        $prepared = $this->repository->prepareUpdateForQuery( $data );
        return $this->query->update( $prepared );
    }

    /**
     * @return int|false
     */
    public function delete()
    {
        return $this->query->delete();
    }

    /**
     * @return QueryBuilder
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }
}
