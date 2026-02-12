<?php

namespace Plugifity\Contract\Abstract;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Interface\ModelInterface;
use Plugifity\Core\DB;
use Plugifity\Core\Database\ModelQueryBuilder;
use Plugifity\Core\Database\Paginator;

/**
 * Abstract Model (Laravel-style)
 *
 * Base for entity models. Subclasses must define TABLE and implement fromRow() and toArray().
 * Provides: primary key, timestamps, find/save/delete, get/set attributes, fillable/guarded, fresh/refresh.
 */
abstract class AbstractModel implements ModelInterface
{
    /**
     * Table name (without prefix). Must be defined in subclass.
     */
    public const TABLE = '';

    /**
     * Primary key column name.
     */
    protected string $primaryKey = 'id';

    /**
     * Whether to automatically manage created_at and updated_at.
     */
    protected bool $timestamps = true;

    /**
     * Name of "created at" column.
     */
    public const CREATED_AT = 'created_at';

    /**
     * Name of "updated at" column.
     */
    public const UPDATED_AT = 'updated_at';

    /**
     * Mass assignable attributes. Empty = all except guarded.
     *
     * @var array<int, string>
     */
    protected array $fillable = [];

    /**
     * Attributes that are not mass assignable.
     *
     * @var array<int, string>
     */
    protected array $guarded = [ 'id' ];

    /**
     * Whether the model exists in the database (loaded or saved).
     */
    public bool $exists = false;

    /**
     * Whether the model was just created in the current request.
     */
    public bool $wasRecentlyCreated = false;

    public static function getTable(): string
    {
        return static::TABLE;
    }

    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return int|string|null
     */
    public function getKey()
    {
        $key = $this->getKeyName();
        return $this->getAttribute( $key );
    }

    /**
     * Set the value of the model's primary key.
     *
     * @param int|string|null $value
     * @return $this
     */
    public function setKey( $value ): static
    {
        $this->setAttribute( $this->getKeyName(), $value );
        return $this;
    }

    /**
     * Get a new query builder for the model's table (raw QueryBuilder).
     *
     * @return \Plugifity\Core\Database\QueryBuilder
     */
    public static function newQuery(): \Plugifity\Core\Database\QueryBuilder
    {
        return DB::table( static::getTable() );
    }

    /**
     * Get a new model query builder (where/get/paginate/insert/update/delete with model hydration).
     *
     * @return ModelQueryBuilder<static>
     */
    public static function query(): ModelQueryBuilder
    {
        return new ModelQueryBuilder( static::class );
    }

    /**
     * Start a query with a where clause.
     *
     * @param string          $column
     * @param mixed           $operatorOrValue
     * @param mixed|null      $value
     * @return ModelQueryBuilder<static>
     */
    public static function where( string $column, $operatorOrValue, $value = null ): ModelQueryBuilder
    {
        return static::query()->where( $column, $operatorOrValue, $value );
    }

    /**
     * Start a query with whereIn.
     *
     * @param string        $column
     * @param array<int, mixed> $values
     * @return ModelQueryBuilder<static>
     */
    public static function whereIn( string $column, array $values ): ModelQueryBuilder
    {
        return static::query()->whereIn( $column, $values );
    }

    /**
     * Start a query with whereNull.
     *
     * @param string $column
     * @return ModelQueryBuilder<static>
     */
    public static function whereNull( string $column ): ModelQueryBuilder
    {
        return static::query()->whereNull( $column );
    }

    /**
     * Start a query with whereNotNull.
     *
     * @param string $column
     * @return ModelQueryBuilder<static>
     */
    public static function whereNotNull( string $column ): ModelQueryBuilder
    {
        return static::query()->whereNotNull( $column );
    }

    /**
     * Get all models (with optional default order). Override in subclass to set default order.
     *
     * @return array<int, static>
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * Insert a single row (with timestamps). Returns insert ID or false.
     *
     * @param array<string, mixed> $data
     * @return int|false
     */
    public static function insert( array $data )
    {
        return static::query()->insert( $data );
    }

    /**
     * Update by primary key (convenience for where(id)->update).
     *
     * @param int|string          $id
     * @param array<string, mixed> $data
     * @return int|false Rows affected
     */
    public static function updateById( $id, array $data )
    {
        $instance = new static();
        return static::query()->where( $instance->getKeyName(), $id )->update( $data );
    }

    /**
     * Find a model by primary key.
     *
     * @param int|string $id
     * @return static|null
     */
    public static function find( $id ): ?static
    {
        $instance = new static();
        $row = static::newQuery()->where( $instance->getKeyName(), $id )->first();
        if ( $row === null ) {
            return null;
        }
        $model = static::fromRow( $row );
        $model->exists = true;
        return $model;
    }

    /**
     * Find a model by primary key or throw.
     *
     * @param int|string $id
     * @return static
     * @throws \RuntimeException
     */
    public static function findOrFail( $id ): static
    {
        $model = static::find( $id );
        if ( $model === null ) {
            throw new \RuntimeException( 'Model not found: ' . static::class . ' with key ' . $id );
        }
        return $model;
    }

    /**
     * Create a new model instance (without saving).
     *
     * @param array<string, mixed> $attributes
     * @return static
     */
    public static function make( array $attributes = [] ): static
    {
        $instance = new static();
        if ( $attributes !== [] ) {
            $instance->fill( $attributes );
        }
        return $instance;
    }

    /**
     * Fill the model with an array of attributes (respects fillable/guarded).
     *
     * @param array<string, mixed> $attributes
     * @return $this
     */
    public function fill( array $attributes ): static
    {
        foreach ( $attributes as $key => $value ) {
            if ( $this->isFillable( $key ) ) {
                $this->setAttribute( $key, $value );
            }
        }
        return $this;
    }

    /**
     * Check if the given key is fillable.
     */
    public function isFillable( string $key ): bool
    {
        if ( in_array( $key, $this->guarded, true ) ) {
            return false;
        }
        if ( $this->fillable === [] ) {
            return true;
        }
        return in_array( $key, $this->fillable, true );
    }

    /**
     * Get an attribute value.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute( string $key )
    {
        if ( property_exists( $this, $key ) ) {
            return $this->{$key};
        }
        return null;
    }

    /**
     * Set an attribute value.
     *
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function setAttribute( string $key, $value ): static
    {
        if ( property_exists( $this, $key ) ) {
            $this->{$key} = $value;
        }
        return $this;
    }

    /**
     * Internal property names to exclude from getAttributes().
     *
     * @var array<int, string>
     */
    protected static array $internalAttributes = [
        'primaryKey',
        'timestamps',
        'fillable',
        'guarded',
        'exists',
        'wasRecentlyCreated',
    ];

    /**
     * Get all attributes as array (model data only, no internal config).
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        $vars = get_object_vars( $this );
        return array_diff_key( $vars, array_flip( static::$internalAttributes ) );
    }

    /**
     * Prepare data for insert (e.g. timestamps). Override in subclass if needed.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareCreate( array $data ): array
    {
        if ( $this->timestamps ) {
            $now = current_time( 'mysql' );
            $data[ static::CREATED_AT ] = $data[ static::CREATED_AT ] ?? $now;
            $data[ static::UPDATED_AT ] = $data[ static::UPDATED_AT ] ?? $now;
        }
        return $data;
    }

    /**
     * Prepare data for update (e.g. updated_at). Override in subclass if needed.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareUpdate( array $data ): array
    {
        if ( $this->timestamps ) {
            $data[ static::UPDATED_AT ] = $data[ static::UPDATED_AT ] ?? current_time( 'mysql' );
        }
        return $data;
    }

    /**
     * Prepare data for insert (static, for use by ModelQueryBuilder).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function prepareCreateForQuery( array $data ): array
    {
        $instance = new static();
        return $instance->prepareCreate( $data );
    }

    /**
     * Prepare data for update (static, for use by ModelQueryBuilder).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function prepareUpdateForQuery( array $data ): array
    {
        $instance = new static();
        return $instance->prepareUpdate( $data );
    }

    /**
     * Save the model to the database (insert or update).
     *
     * @return bool
     */
    public function save(): bool
    {
        $data = $this->toArray();
        $keyName = $this->getKeyName();
        $keyValue = $this->getKey();

        if ( $keyValue === null || $keyValue === '' ) {
            $data = $this->prepareCreate( $data );
            $id = static::newQuery()->insert( $data );
            if ( $id === false ) {
                return false;
            }
            $this->setAttribute( $keyName, is_numeric( $id ) ? (int) $id : $id );
            $this->exists = true;
            $this->wasRecentlyCreated = true;
            return true;
        }

        $data = $this->prepareUpdate( $data );
        $affected = static::newQuery()->where( $keyName, $keyValue )->update( $data );
        return $affected !== false;
    }

    /**
     * Delete the model from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        $keyValue = $this->getKey();
        if ( $keyValue === null || $keyValue === '' ) {
            return false;
        }
        $affected = static::newQuery()->where( $this->getKeyName(), $keyValue )->delete();
        if ( $affected !== false && $affected > 0 ) {
            $this->exists = false;
            return true;
        }
        return $affected !== false;
    }

    /**
     * Reload the model from the database (new instance).
     *
     * @return static|null
     */
    public function fresh(): ?static
    {
        $keyValue = $this->getKey();
        if ( $keyValue === null || $keyValue === '' ) {
            return null;
        }
        return static::find( $keyValue );
    }

    /**
     * Reload the model from the database (mutate current instance).
     *
     * @return $this
     */
    public function refresh(): static
    {
        $fresh = $this->fresh();
        if ( $fresh === null ) {
            return $this;
        }
        foreach ( $fresh->getAttributes() as $key => $value ) {
            $this->setAttribute( $key, $value );
        }
        return $this;
    }

    /**
     * Convert the model to its string representation (JSON).
     */
    public function toJson( int $options = 0 ): string
    {
        return (string) wp_json_encode( $this->toArray(), $options );
    }

    /**
     * Convert the model to array for insert/update. Must be implemented in subclass.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Create model instance from DB row. Must be implemented in subclass.
     */
    abstract public static function fromRow( object $row ): static;

    /**
     * Get attribute via property access.
     *
     * @param string $key
     * @return mixed
     */
    public function __get( string $key )
    {
        return $this->getAttribute( $key );
    }

    /**
     * Set attribute via property access.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function __set( string $key, $value )
    {
        $this->setAttribute( $key, $value );
    }

    /**
     * Check if attribute is set.
     */
    public function __isset( string $key ): bool
    {
        return $this->getAttribute( $key ) !== null;
    }
}
