<?php

namespace Pluto\Orm\MySQL;

use \Closure;
use \PDO;

class QueryBuilder
{
    protected string $table;
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $selects = ['*'];
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $joins = [];
    protected array $groups = [];
    protected array $havings = [];
    protected array $with = [];
    protected array $rows = [];
    protected ?string $modelClass = null;

    public function __construct(string $table)
    {
        $this->table = $table;
        Database::connect([
            'host' => getenv('DB_HOST'),
            'database' => getenv('DB_NAME'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
            'driver' => getenv('DB_DRIVER') ?: 'mysql',
            'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        ]);
    }

    public static function table(string $table): self
    {
        return new self($table);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getModel(): ?string
    {
        return $this->modelClass;
    }


    public function select(...$cols): self
    {
        if (count($cols) === 1 && is_array($cols[0])) $cols = $cols[0];
        $this->selects = $cols ?: ['*'];
        return $this;
    }

    public function model(string $class): self
    {
        $this->modelClass = $class;
        return $this;
    }

    protected function addWhere(string $boolean, $column, $operator = null, $value = null): self
    {
        if (is_array($column)) {
            foreach ($column as $k => $v) {
                $this->addWhere('and', $k, '=', $v);
            }
            return $this;
        }

        if ($column instanceof Closure) {
            $nested = new self($this->table);
            $column($nested);
            $this->wheres[] = ['type' => 'nested', 'boolean' => $boolean, 'query' => $nested];
            $this->bindings = array_merge($this->bindings, $nested->bindings);
            return $this;
        }

        if (func_num_args() === 2) { // where('username','value') shorthand
            $value = $operator;
            $operator = '=';
        }

        if ($operator === null) $operator = '=';

        $placeholder = ':p' . count($this->bindings);
        $this->wheres[] = ['type' => 'basic', 'boolean' => $boolean, 'column' => $column, 'operator' => $operator, 'placeholder' => $placeholder];
        $this->bindings[$placeholder] = $value;
        return $this;
    }


    public function where($column, $operator = null, $value = null): self
    {
        if ($column instanceof \Closure) {
            return $this->addNestedWhere('and', $column);
        }

        return $this->addWhere('and', $column, $operator, $value);
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        if ($column instanceof \Closure) {
            return $this->addNestedWhere('or', $column);
        }

        return $this->addWhere('or', $column, $operator, $value);
    }

    protected function addNestedWhere(string $boolean, \Closure $callback): self
    {
        $nested = new self($this->table);
        $nested->model($this->modelClass);

        $callback($nested);

        $this->wheres[] = [
            'type'    => 'nested',
            'boolean' => $boolean,
            'query'   => $nested
        ];

        $this->bindings = array_merge($this->bindings, $nested->bindings);

        return $this;
    }

    public function whereIn(string $column, array $values, string $boolean = 'and'): self
    {
        $placeholders = [];
        foreach ($values as $v) {
            $ph = ':p' . count($this->bindings);
            $this->bindings[$ph] = $v;
            $placeholders[] = $ph;
        }
        $this->wheres[] = ['type' => 'in', 'boolean' => $boolean, 'column' => $column, 'placeholders' => $placeholders, 'not' => false];
        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'and'): self
    {
        $this->whereIn($column, $values, $boolean);
        $this->wheres[array_key_last($this->wheres)]['not'] = true;
        return $this;
    }

    public function whereNull(string $column, string $boolean = 'and'): self
    {
        $this->wheres[] = ['type' => 'null', 'boolean' => $boolean, 'column' => $column, 'not' => false];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        $this->wheres[] = ['type' => 'null', 'boolean' => $boolean, 'column' => $column, 'not' => true];
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = compact('type', 'table', 'first', 'operator', 'second');
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [$column, strtoupper($direction)];
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    // alias
    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    public function groupBy(...$columns): self
    {
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    public function having(string $column, string $operator, $value): self
    {
        $ph = ':p' . count($this->bindings);
        $this->bindings[$ph] = $value;
        $this->havings[] = compact('column', 'operator', 'ph');
        return $this;
    }

    public function with(array $relations): self
    {
        // relations in form ['relation', 'relation2' => fn($q){...}]
        $this->with = $relations;
        return $this;
    }

    protected function compileSelect(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->selects) . ' FROM ' . $this->table;
        if ($this->joins) {
            foreach ($this->joins as $j) {
                $sql .= " {$j['type']} JOIN {$j['table']} ON {$j['first']} {$j['operator']} {$j['second']}";
            }
        }
        if ($this->wheres) {
            $sql .= ' WHERE ' . $this->compileWheres($this->wheres);
        }
        if ($this->groups) $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        if ($this->havings) {
            $parts = array_map(fn($h) => "{$h['column']} {$h['operator']} {$h['ph']}", $this->havings);
            $sql .= ' HAVING ' . implode(' AND ', $parts);
        }
        if ($this->orders) $sql .= ' ORDER BY ' . implode(', ', array_map(fn($o) => "{$o[0]} {$o[1]}", $this->orders));
        if ($this->limit !== null) $sql .= ' LIMIT ' . (int)$this->limit;
        if ($this->offset !== null) $sql .= ' OFFSET ' . (int)$this->offset;
        return $sql;
    }


    public function debugSql(): string
    {
        $sql = $this->toSql();

        $bindings = $this->bindings;

        foreach ($bindings as $key => $value) {
            if (is_string($value)) {
                $value = "'" . str_replace("'", "''", $value) . "'";
            } elseif ($value === null) {
                $value = 'NULL';
            }

            $sql = preg_replace('/' . preg_quote($key, '/') . '\b/', $value, $sql, 1);
        }

        return $sql;
    }

    protected function compileWheres(array $wheres): string
    {
        $sql = '';
        foreach ($wheres as $i => $w) {
            $boolean = ($i === 0) ? '' : ' ' . strtoupper($w['boolean']) . ' ';
            if ($w['type'] === 'basic') {
                $sql .= $boolean . "{$w['column']} {$w['operator']} {$w['placeholder']}";
            } elseif ($w['type'] === 'in') {
                $list = implode(', ', $w['placeholders']);
                $not = $w['not'] ? ' NOT' : '';
                $sql .= $boolean . "{$w['column']}{$not} IN ({$list})";
            } elseif ($w['type'] === 'null') {
                $not = $w['not'] ? ' IS NOT NULL' : ' IS NULL';
                $sql .= $boolean . "{$w['column']}{$not}";
            } elseif ($w['type'] === 'nested') {
                $nestedSql = $w['query']->compileWheres($w['query']->wheres);
                $sql .= $boolean . '(' . $nestedSql . ')';
            }
        }
        return $sql;
    }

    public function toSql(): string
    {
        return $this->compileSelect();
    }

    public function get(array $columns = []): array
    {
        if ($columns) $this->select(...$columns);
        $sql = $this->compileSelect();
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($this->bindings);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->rows = $rows;
        if ($this->modelClass) {
            $models = array_map(fn($r) => new $this->modelClass($r), $rows);
            if ($this->with) $this->eagerLoadRelations($models);
            return $models;
        }

        return $rows;
    }

    public function toArray(array $columns = []): array
    {
        if (empty($this->rows)) {
            if ($columns) $this->select(...$columns);
            $sql = $this->compileSelect();
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($this->bindings);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->rows = $rows;
        }
        return $this->rows;
    }


    public function first(): ?object
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function count(): int
    {
        $prev = $this->selects;
        $this->selects = ['COUNT(*) as aggregate'];
        $sql = $this->compileSelect();
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($this->bindings);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->selects = $prev;
        return (int)($row['aggregate'] ?? 0);
    }

    public function insert(array $data): bool
    {
        $cols = array_keys($data);
        $placeholders = [];
        $bindings = [];
        foreach ($data as $k => $v) {
            $ph = ':' . $k;
            $placeholders[] = $ph;
            $bindings[$ph] = $v;
        }
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = Database::pdo()->prepare($sql);
        return $stmt->execute($bindings);
    }

    public function insertGetId(array $data): int
    {
        $this->insert($data);
        return (int)Database::pdo()->lastInsertId();
    }

    public function update(array $data): int
    {
        $sets = [];
        $bindings = $this->bindings; // keep where bindings
        foreach ($data as $k => $v) {
            $ph = ':u' . count($bindings);
            $bindings[$ph] = $v;
            $sets[] = "{$k} = {$ph}";
        }
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets);
        if ($this->wheres) $sql .= ' WHERE ' . $this->compileWheres($this->wheres);
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table;
        if ($this->wheres) $sql .= ' WHERE ' . $this->compileWheres($this->wheres);
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }

    // Raw expression
    public function raw(string $sql, array $bindings = [])
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function eagerLoadRelations(array &$models): void
    {
        if (!$this->modelClass) return;
        $m = new $this->modelClass();
        foreach ($this->with as $key => $constraint) {
            $name = is_int($key) ? $constraint : $key;
            $constraintFn = is_int($key) ? null : $constraint;
            if (!method_exists($m, $name)) continue;
            // collect keys
            $relation = $m->$name();
            if (!($relation instanceof Relation)) continue;
            $relatedModels = $relation->eagerLoad($models, $constraintFn);
            // attach to parents inside relation->attach
            $relation->attach($models, $relatedModels);
        }
    }


    public function whereHas(string $relation, \Closure $callback): self
    {
        return $this->addWhereHas('and', $relation, $callback);
    }

    public function orWhereHas(string $relation, \Closure $callback): self
    {
        return $this->addWhereHas('or', $relation, $callback);
    }

    protected function addWhereHas(string $boolean, string $relation, \Closure $callback): self
    {
        if (!$this->modelClass) {
            throw new \Exception('whereHas requires model context');
        }

        $model = new $this->modelClass();

        if (!method_exists($model, $relation)) {
            throw new \Exception("Relation {$relation} not found");
        }

        /** @var Relation $rel */
        $rel = $model->$relation();

        $sub = QueryBuilder::table($rel->getRelatedTable());
        $sub->model($rel->getRelatedClass());

        $callback($sub);

        // EXISTS subquery
        $sql = 'EXISTS (
        SELECT 1 FROM ' . $rel->getRelatedTable() . '
        WHERE ' . $rel->getRelatedTable() . '.' . $rel->getForeignKeyName() . ' = '
            . $this->table . '.' . $rel->getLocalKeyName() . '
        AND ' . $sub->compileWheres($sub->wheres) . '
    )';

        $this->wheres[] = [
            'type'    => 'raw',
            'boolean' => $boolean,
            'sql'     => $sql
        ];

        $this->bindings = array_merge($this->bindings, $sub->bindings);

        return $this;
    }
}
