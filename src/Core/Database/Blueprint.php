<?php

namespace Plugifity\Core\Database;

/**
 * Blueprint
 *
 * Laravel-style schema blueprint for defining table columns.
 * Builds dbDelta-compatible SQL for WordPress (each field on own line, KEY not INDEX, etc.).
 */
class Blueprint
{
    /**
     * Table name
     *
     * @var string
     */
    protected string $table;

    /**
     * Column definitions: [name => ['type' => ..., 'modifiers' => [...]]]
     *
     * @var array<string, array>
     */
    protected array $columns = [];

    /**
     * Primary key column(s)
     *
     * @var array<int, string>
     */
    protected array $primary = [];

    /**
     * Index/Key definitions: [name => ['type' => 'index'|'unique', 'columns' => [...]]]
     *
     * @var array<string, array>
     */
    protected array $indexes = [];

    /**
     * Foreign key definitions (stored for reference; wpdb/dbDelta handling can be extended)
     *
     * @var array<int, array>
     */
    protected array $foreignKeys = [];

    /**
     * @param string $table
     */
    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Big incrementing ID (primary key).
     *
     * @param string $column
     * @return $this
     */
    public function id(string $column = 'id'): self
    {
        return $this->bigIncrements($column);
    }

    /**
     * Big integer auto-increment (primary key).
     *
     * @param string $column
     * @return $this
     */
    public function bigIncrements(string $column): self
    {
        $this->columns[$column] = [
            'type'     => 'bigint(20)',
            'unsigned' => true,
            'auto_increment' => true,
            'nullable' => false,
        ];
        $this->primary = [$column];
        return $this;
    }

    /**
     * Integer auto-increment.
     *
     * @param string $column
     * @return $this
     */
    public function increments(string $column): self
    {
        $this->columns[$column] = [
            'type'     => 'int(11)',
            'unsigned' => true,
            'auto_increment' => true,
            'nullable' => false,
        ];
        $this->primary = [$column];
        return $this;
    }

    /**
     * String (VARCHAR).
     *
     * @param string $column
     * @param int $length
     * @return $this
     */
    public function string(string $column, int $length = 255): self
    {
        $this->columns[$column] = [
            'type'    => "varchar({$length})",
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Text column.
     *
     * @param string $column
     * @return $this
     */
    public function text(string $column): self
    {
        $this->columns[$column] = [
            'type'    => 'text',
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Long text.
     *
     * @param string $column
     * @return $this
     */
    public function longText(string $column): self
    {
        $this->columns[$column] = [
            'type'    => 'longtext',
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Integer.
     *
     * @param string $column
     * @param int $length
     * @return $this
     */
    public function integer(string $column, int $length = 11): self
    {
        $this->columns[$column] = [
            'type'    => "int({$length})",
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Big integer.
     *
     * @param string $column
     * @return $this
     */
    public function bigInteger(string $column): self
    {
        $this->columns[$column] = [
            'type'    => 'bigint(20)',
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Small integer.
     *
     * @param string $column
     * @return $this
     */
    public function smallInteger(string $column): self
    {
        $this->columns[$column] = [
            'type'    => 'smallint(6)',
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Tiny integer.
     *
     * @param string $column
     * @return $this
     */
    public function tinyInteger(string $column): self
    {
        $this->columns[$column] = [
            'type'    => 'tinyint(4)',
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Unsigned big integer (e.g. for foreign keys).
     *
     * @param string $column
     * @return $this
     */
    public function unsignedBigInteger(string $column): self
    {
        $this->columns[$column] = [
            'type'     => 'bigint(20)',
            'unsigned' => true,
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Boolean (tinyint 0/1).
     *
     * @param string $column
     * @return $this
     */
    public function boolean(string $column): self
    {
        $this->columns[$column] = [
            'type'    => 'tinyint(1)',
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Decimal.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @return $this
     */
    public function decimal(string $column, int $total = 8, int $places = 2): self
    {
        $this->columns[$column] = [
            'type'    => "decimal({$total},{$places})",
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Float.
     *
     * @param string $column
     * @param int|null $precision
     * @return $this
     */
    public function float(string $column, ?int $precision = null): self
    {
        $type = $precision !== null ? "float({$precision})" : 'float';
        $this->columns[$column] = [
            'type'    => $type,
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Date.
     *
     * @param string $column
     * @return $this
     */
    public function date(string $column): self
    {
        $this->columns[$column] = [
            'type'    => 'date',
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Date time.
     *
     * @param string $column
     * @return $this
     */
    public function dateTime(string $column): self
    {
        $this->columns[$column] = [
            'type'    => 'datetime',
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Timestamp.
     *
     * @param string $column
     * @return $this
     */
    public function timestamp(string $column): self
    {
        $this->columns[$column] = [
            'type'    => 'datetime',
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Created at and updated at timestamps.
     *
     * @return $this
     */
    public function timestamps(): self
    {
        $this->dateTime('created_at')->nullable();
        $this->dateTime('updated_at')->nullable();
        return $this;
    }

    /**
     * Soft deletes (deleted_at).
     *
     * @param string $column
     * @return $this
     */
    public function softDeletes(string $column = 'deleted_at'): self
    {
        $this->columns[$column] = [
            'type'    => 'datetime',
            'nullable' => true,
        ];
        return $this;
    }

    /**
     * JSON column.
     *
     * @param string $column
     * @return $this
     */
    public function json(string $column): self
    {
        $this->columns[$column] = [
            'type'    => 'longtext',
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * Remember token (nullable varchar 100).
     *
     * @return $this
     */
    public function rememberToken(): self
    {
        $this->columns['remember_token'] = [
            'type'    => 'varchar(100)',
            'nullable' => true,
        ];
        return $this;
    }

    /**
     * Foreign key column (unsigned bigint).
     *
     * @param string $column
     * @return $this
     */
    public function foreignId(string $column): self
    {
        return $this->unsignedBigInteger($column);
    }

    /**
     * Add unique index.
     *
     * @param string|array<int, string> $columns
     * @param string|null $name
     * @return $this
     */
    public function unique($columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_unique';
        $this->indexes[$name] = ['type' => 'unique', 'columns' => $columns];
        return $this;
    }

    /**
     * Add index.
     *
     * @param string|array<int, string> $columns
     * @param string|null $name
     * @return $this
     */
    public function index($columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_index';
        $this->indexes[$name] = ['type' => 'index', 'columns' => $columns];
        return $this;
    }

    /**
     * Foreign key constraint (stores for reference; actual FK creation via raw SQL if needed).
     *
     * @param string $column
     * @param string|null $name
     * @return $this
     */
    public function foreign(string $column, ?string $name = null): self
    {
        $this->foreignKeys[] = ['column' => $column, 'name' => $name];
        return $this;
    }

    /**
     * Set column nullable (chain on last column - we need to track "last" for fluent).
     * Apply to a column by name for simplicity in Blueprint.
     *
     * @param string|null $column If null, apply to last added column
     * @return $this
     */
    public function nullable(?string $column = null): self
    {
        if ($column !== null) {
            if (isset($this->columns[$column])) {
                $this->columns[$column]['nullable'] = true;
            }
            return $this;
        }
        $keys = array_keys($this->columns);
        $last = end($keys);
        if ($last !== false) {
            $this->columns[$last]['nullable'] = true;
        }
        return $this;
    }

    /**
     * Set default value (for last column or given column).
     *
     * @param mixed $value
     * @param string|null $column
     * @return $this
     */
    public function default($value, ?string $column = null): self
    {
        $col = $column;
        if ($col === null) {
            $keys = array_keys($this->columns);
            $col = end($keys) ?: null;
        }
        if ($col !== null && isset($this->columns[$col])) {
            $this->columns[$col]['default'] = $value;
        }
        return $this;
    }

    /**
     * Set column to use current timestamp.
     *
     * @param string|null $column
     * @return $this
     */
    public function useCurrent(?string $column = null): self
    {
        $col = $column;
        if ($col === null) {
            $keys = array_keys($this->columns);
            $col = end($keys) ?: null;
        }
        if ($col !== null && isset($this->columns[$col])) {
            $this->columns[$col]['default'] = 'CURRENT_TIMESTAMP';
        }
        return $this;
    }

    /**
     * Build CREATE TABLE SQL in dbDelta-compatible format for WordPress.
     * Uses lowercase types, one field per line, PRIMARY KEY with two spaces, KEY not INDEX.
     *
     * @param string $charsetCollate From $wpdb->get_charset_collate()
     * @return string
     */
    public function toSql(string $charsetCollate = ''): string
    {
        $lines = [];

        foreach ($this->columns as $name => $def) {
            $line = $name . ' ' . $def['type'];
            if (!empty($def['unsigned'])) {
                $line .= ' unsigned';
            }
            $line .= ($def['nullable'] ?? false) ? ' NULL' : ' NOT NULL';
            if (isset($def['auto_increment']) && $def['auto_increment']) {
                $line .= ' AUTO_INCREMENT';
            }
            if (array_key_exists('default', $def)) {
                $d = $def['default'];
                if ($d === 'CURRENT_TIMESTAMP') {
                    $line .= ' DEFAULT CURRENT_TIMESTAMP';
                } else {
                    $line .= " DEFAULT '" . addslashes((string) $d) . "'";
                }
            }
            $lines[] = $line;
        }

        // PRIMARY KEY (dbDelta: two spaces between PRIMARY KEY and (id))
        if ($this->primary !== []) {
            $lines[] = 'PRIMARY KEY  (' . implode(',', $this->primary) . ')';
        }

        foreach ($this->indexes as $keyName => $idx) {
            $cols = implode(',', $idx['columns']);
            $lines[] = (($idx['type'] ?? '') === 'unique')
                ? "UNIQUE KEY {$keyName} ({$cols})"
                : "KEY {$keyName} ({$cols})";
        }

        $body = implode(",\n", $lines);
        return "CREATE TABLE {$this->table} (\n{$body}\n) {$charsetCollate}";
    }

    /**
     * Get table name.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get columns (for ALTER TABLE etc).
     *
     * @return array<string, array>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get primary key columns.
     *
     * @return array<int, string>
     */
    public function getPrimary(): array
    {
        return $this->primary;
    }

    /**
     * Get indexes.
     *
     * @return array<string, array>
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }
}
