<?php

namespace Plugifity\Core\Database;

/**
 * QueryBuilder
 *
 * Laravel-style fluent query builder using WordPress wpdb.
 * select, where, insert, update, delete, get, first, find.
 */
class QueryBuilder
{
    protected \wpdb $wpdb;
    protected string $table;
    protected string $prefix;
    protected array $wheres = [];
    protected array $bindings = [];
    protected ?string $select = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $orderBy = [];
    protected array $joinClauses = [];

    public function __construct(\wpdb $wpdb, string $table)
    {
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix;
        $this->table = strpos($table, $this->prefix) === 0 ? $table : $this->prefix . $table;
    }

    /**
     * Set columns to select.
     *
     * @param string|array<int, string> $columns
     * @return $this
     */
    public function select($columns = ['*']): self
    {
        $this->select = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    /**
     * Add WHERE clause.
     *
     * @param string $column
     * @param mixed $operatorOrValue
     * @param mixed|null $value
     * @return $this
     */
    public function where(string $column, $operatorOrValue, $value = null): self
    {
        if ($value === null) {
            $value = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = $operatorOrValue;
        }
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * WHERE IN.
     *
     * @param string $column
     * @param array<int, mixed> $values
     * @return $this
     */
    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
        ];
        foreach ($values as $v) {
            $this->bindings[] = $v;
        }
        return $this;
    }

    /**
     * WHERE NULL.
     *
     * @param string $column
     * @return $this
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = ['type' => 'null', 'column' => $column];
        return $this;
    }

    /**
     * WHERE NOT NULL.
     *
     * @param string $column
     * @return $this
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['type' => 'not_null', 'column' => $column];
        return $this;
    }

    /**
     * ORDER BY.
     *
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = ['column' => $column, 'direction' => strtoupper($direction)];
        return $this;
    }

    /**
     * LIMIT.
     *
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * OFFSET.
     *
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Build SELECT SQL and return results.
     *
     * @return array<int, object>
     */
    public function get(): array
    {
        $sql = $this->toSql();
        if ($sql === '') {
            return [];
        }
        $prepared = $this->bindings !== [] ? $this->wpdb->prepare($sql, ...$this->bindings) : $sql;
        $results = $this->wpdb->get_results($prepared);
        return $results !== null ? $results : [];
    }

    /**
     * Get first row.
     *
     * @return object|null
     */
    public function first(): ?object
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    /**
     * Find by primary key (id).
     *
     * @param int|string $id
     * @param string $column
     * @return object|null
     */
    public function find($id, string $column = 'id'): ?object
    {
        return $this->where($column, $id)->first();
    }

    /**
     * Insert row(s).
     *
     * @param array<string, mixed> $data Single row, or pass array of rows for bulk (same keys)
     * @return int|false Rows affected or last insert id for single row, false on failure
     */
    public function insert(array $data)
    {
        $single = isset($data[0]) && is_array($data[0]) ? false : true;
        if ($single) {
            $result = $this->wpdb->insert($this->table, $data);
            return $result !== false ? $this->wpdb->insert_id : false;
        }
        $affected = 0;
        foreach ($data as $row) {
            $r = $this->wpdb->insert($this->table, $row);
            if ($r !== false) {
                $affected++;
            }
        }
        return $affected;
    }

    /**
     * Update rows.
     *
     * @param array<string, mixed> $data
     * @return int|false Number of rows updated, false on failure
     */
    public function update(array $data)
    {
        $set = [];
        $values = [];
        foreach ($data as $col => $val) {
            $set[] = '`' . esc_sql($col) . '` = %s';
            $values[] = $val;
        }
        $whereSql = $this->buildWhereSql();
        if ($whereSql !== '') {
            $values = array_merge($values, $this->bindings);
            $sql = 'UPDATE `' . esc_sql($this->table) . '` SET ' . implode(', ', $set) . ' WHERE ' . $whereSql;
            $prepared = $this->wpdb->prepare($sql, ...$values);
            $this->wpdb->query($prepared);
            return $this->wpdb->rows_affected;
        }
        $sql = 'UPDATE `' . esc_sql($this->table) . '` SET ' . implode(', ', $set);
        $prepared = $this->wpdb->prepare($sql, ...$values);
        $this->wpdb->query($prepared);
        return $this->wpdb->rows_affected;
    }

    /**
     * Delete rows.
     *
     * @return int|false Rows affected, false on failure
     */
    public function delete()
    {
        $whereSql = $this->buildWhereSql();
        if ($whereSql === '') {
            $sql = 'DELETE FROM `' . esc_sql($this->table) . '`';
            $this->wpdb->query($sql);
            return $this->wpdb->rows_affected;
        }
        $sql = 'DELETE FROM `' . esc_sql($this->table) . '` WHERE ' . $whereSql;
        $prepared = $this->bindings !== [] ? $this->wpdb->prepare($sql, ...$this->bindings) : $sql;
        $this->wpdb->query($prepared);
        return $this->wpdb->rows_affected;
    }

    /**
     * Count rows.
     *
     * @param string $column
     * @return int
     */
    public function count(string $column = '*'): int
    {
        $builder = clone $this;
        $builder->select = $column === '*' ? 'COUNT(*)' : 'COUNT(`' . esc_sql($column) . '`)';
        $builder->limit = null;
        $builder->offset = null;
        $builder->orderBy = [];
        $sql = $builder->toSql();
        if ($sql === '') {
            return 0;
        }
        $prepared = $builder->bindings !== [] ? $this->wpdb->prepare($sql, ...$builder->bindings) : $sql;
        $result = $this->wpdb->get_var($prepared);
        return (int) $result;
    }

    /**
     * Check if any row exists.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Build SELECT SQL string (without prepare placeholders for get()).
     *
     * @return string
     */
    protected function toSql(): string
    {
        $select = $this->select ?? '*';
        $sql = 'SELECT ' . $select . ' FROM `' . esc_sql($this->table) . '`';
        $whereSql = $this->buildWhereSql();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }
        if ($this->orderBy !== []) {
            $parts = [];
            foreach ($this->orderBy as $o) {
                $parts[] = '`' . esc_sql($o['column']) . '` ' . ($o['direction'] === 'DESC' ? 'DESC' : 'ASC');
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . (int) $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . (int) $this->offset;
        }
        return $sql;
    }

    /**
     * Build WHERE clause (uses %s placeholders; bindings in $this->bindings).
     *
     * @return string
     */
    protected function buildWhereSql(): string
    {
        if ($this->wheres === []) {
            return '';
        }
        $parts = [];
        $idx = 0;
        foreach ($this->wheres as $w) {
            if ($w['type'] === 'basic') {
                $parts[] = '`' . esc_sql($w['column']) . '` ' . $w['operator'] . ' %s';
                $idx++;
            } elseif ($w['type'] === 'in') {
                $placeholders = array_fill(0, count($w['values']), '%s');
                $parts[] = '`' . esc_sql($w['column']) . '` IN (' . implode(',', $placeholders) . ')';
            } elseif ($w['type'] === 'null') {
                $parts[] = '`' . esc_sql($w['column']) . '` IS NULL';
            } elseif ($w['type'] === 'not_null') {
                $parts[] = '`' . esc_sql($w['column']) . '` IS NOT NULL';
            }
        }
        return implode(' AND ', $parts);
    }

    /**
     * Get underlying wpdb.
     *
     * @return \wpdb
     */
    public function getWpdb(): \wpdb
    {
        return $this->wpdb;
    }

    /**
     * Get table name (with prefix).
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }
}
