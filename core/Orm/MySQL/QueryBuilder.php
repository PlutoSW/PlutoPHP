<?php

namespace Pluto\Orm\MySQL;

use PDO;
use Pluto\Model;


class QueryBuilder
{
    protected PDO $pdo;
    public string $table;
    protected Model $model;

    protected string $select = '*';
    protected array $wheres = [];
    protected array $bindings = [];
    protected ?string $orderBy = null;
    protected ?string $limit = null;
    protected ?string $offset = null;
    protected array $with = [];
    protected array $joins = [];

    public function __construct(string $modelClass)
    {
        $this->pdo = Database::getInstance();
        $this->model = new $modelClass;
        $this->table = $this->model->getTableName();
    }

    protected function quoteIdentifier(string $identifier): string
    {
        // Handle table.column format
        if (str_contains($identifier, '.')) {
            return implode('.', array_map(fn ($part) => "`{$part}`", explode('.', $identifier)));
        }

        // Handle simple column name
        return "`{$identifier}`";
    }

    public function where($column, $operator = null, $value = null): self
    {

        if ($column instanceof \Closure) {
            $this->addNestedWhereQuery($column);
            return $this;
        }
        $quotedColumn = $this->quoteIdentifier($column);

        if (is_null($value) && in_array(strtoupper(trim($operator)), ['IS', 'IS NOT'])) {
            $boolean = empty($this->wheres) ? '' : 'AND ';
            $this->wheres[] = "{$boolean}{$quotedColumn} {$operator} NULL";
        } else {
            $boolean = empty($this->wheres) ? '' : 'AND ';
            $this->wheres[] = "{$boolean}{$quotedColumn} {$operator} ?";
            $this->bindings[] = $value;
        }
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null, $boolean = 'OR'): self
    {

        if ($column instanceof \Closure) {
            $this->addNestedWhereQuery($column, 'OR');
            return $this;
        }
        $quotedColumn = $this->quoteIdentifier($column);

        if (is_null($value) && in_array(strtoupper(trim($operator)), ['IS', 'IS NOT'])) {
            $boolean = empty($this->wheres) ? '' : "{$boolean} ";
            $this->wheres[] = "{$boolean}{$quotedColumn} {$operator} NULL";
        } else {
            $boolean = empty($this->wheres) ? '' : "{$boolean} ";
            $this->wheres[] = "{$boolean}{$quotedColumn} {$operator} ?";
            $this->bindings[] = $value;
        }
        return $this;
    }

    /**
     * Add a nested where statement to the query.
     *
     * @param \Closure $callback
     * @param string $boolean
     * @return void
     */
    protected function addNestedWhereQuery(\Closure $callback, string $boolean = 'AND')
    {
        $query = new self(get_class($this->model));
        $callback($query);

        if (!empty($query->wheres)) {
            $nestedWheres = ltrim(implode(' ', $query->wheres), 'AND OR ');
            $this->wheres[] = (empty($this->wheres) ? '' : "{$boolean} ") . "({$nestedWheres})";
            $this->bindings = array_merge($this->bindings, $query->getBindings());
        }
    }

    /**
     * Add a "where has" clause to the query.
     *
     * @param string $relationName
     * @param callable $callback
     * @param string $boolean
     * @return self
     */
    public function whereHas(string $relationName, callable $callback, string $boolean = 'AND'): self
    {
        $relation = $this->model->{$relationName}();
        $relationQuery = $relation->getQuery();


        call_user_func($callback, $relationQuery);

        $relationTable = $relationQuery->table;
        $foreignKey = $relation->getForeignKeyName();
        $ownerKey = $relation->getOwnerKeyName();

        $subQuery = "EXISTS (SELECT * FROM `{$relationTable}` WHERE `{$this->table}`.`{$foreignKey}` = `{$relationTable}`.`{$ownerKey}` AND " . ltrim(implode(' ', $relationQuery->wheres), 'AND OR ') . ")";

        $this->wheres[] = (empty($this->wheres) ? '' : "{$boolean} ") . $subQuery;
        $this->bindings = array_merge($this->bindings, $relationQuery->bindings);

        return $this;
    }

    public function orWhereHas(string $relationName, callable $callback): self
    {
        return $this->whereHas($relationName, $callback, 'OR');
    }

    public function join(string $table, string $firstColumn, string $operator, string $secondColumn, string $type = 'INNER', string $as = ""): self
    {
        $jn = "{$type} JOIN `{$table}` ON {$firstColumn} {$operator} {$secondColumn}";
        if ($as) {
            $jn .= " AS {$as}";
        }
        $this->joins[] = $jn;
        return $this;
    }

    public function leftJoin(string $table, string $firstColumn, string $operator, string $secondColumn, string $as = ""): self
    {
        return $this->join($table, $firstColumn, $operator, $secondColumn, 'LEFT', $as);
    }

    public function rightJoin(string $table, string $firstColumn, string $operator, string $secondColumn, string $as = ""): self
    {
        return $this->join($table, $firstColumn, $operator, $secondColumn, 'RIGHT', $as);
    }

    public function select(string|array $columns): self
    {
        $this->select = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        if (empty($values)) {
            $this->wheres[] = ($boolean === 'AND' ? '1=0' : '0=1');
            return $this;
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $boolean = empty($this->wheres) ? '' : "{$boolean} ";
        $this->wheres[] = "{$boolean}`{$column}` IN ({$placeholders})";
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "ORDER BY {$column} {$direction}";
        return $this;
    }

    public function limit(int $number): self
    {
        $this->limit = "LIMIT $number";
        return $this;
    }

    public function offset(int $number): self
    {
        $this->offset = "OFFSET $number";
        return $this;
    }

    public function with(array|string $relations): self
    {
        $this->with = is_array($relations) ? $relations : [$relations];
        return $this;
    }

    public function get(): Collection
    {
        $sql = $this->toSql();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $collection = $this->hydrate($results);

        if (!empty($this->with)) {
            $this->loadRelations($collection);
        }

        return $collection;
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . ltrim(implode(' ', $this->wheres), 'AND OR ');
        }

        if ($this->orderBy) {
            $sql .= " " . $this->orderBy;
        }

        if ($this->limit) {
            $sql .= " " . $this->limit;
        }

        if ($this->offset) {
            $sql .= " " . $this->offset;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return (int) $stmt->fetchColumn();
    }

    public function debugSql($sql=null): void
    {
        $sql = $sql ?? $this->toSql();
        $bindings = $this->getBindings();

        $i = 0;
        $rawSql = preg_replace_callback('/\?/', function ($match) use ($bindings, &$i) {
            if (!isset($bindings[$i])) {
                return $match[0]; // Eşleşen ? yoksa, olduğu gibi bırak
            }
            $value = $bindings[$i];
            $i++;

            if (is_null($value)) return 'NULL';
            if (is_string($value)) return "'" . addslashes($value) . "'";
            return $value;
        }, $sql);

        echo $rawSql;
        exit;
    }

    public function all(): Collection
    {
        return $this->get();
    }

    public function first()
    {
        $this->limit(1);
        $results = $this->get();
        return $results->first();
    }

    public function find(int $id)
    {
        return $this->where('id', '=', $id);
    }

    public function insert(array $values): bool
    {
        $columns = implode(', ', array_keys($values));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_values($values));
    }

    public function update(array $values): bool
    {
        $setClauses = [];
        $updateBindings = [];
        foreach ($values as $column => $value) {
            $setClauses[] = "{$column} = ?";
            $updateBindings[] = $value;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . ltrim(implode(' ', $this->wheres), 'AND OR ');
        }
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_merge($updateBindings, $this->bindings));
    }

    public function sum(string $column): float
    {
        $sql = "SELECT SUM(`{$column}`) FROM {$this->table}";
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . ltrim(implode(' ', $this->wheres), 'AND OR ');
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetchColumn();
        return (float) ($result ?? 0.0);
    }

    public function delete(): bool
    {
        $sql = "DELETE FROM {$this->table}";
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . ltrim(implode(' ', $this->wheres), 'AND OR ');
        }
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($this->bindings);
    }

    public function softDelete(): bool
    {
        $sql = "UPDATE {$this->table} SET deleted_at = NOW()";
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . ltrim(implode(' ', $this->wheres), 'AND OR ');
        }
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($this->bindings);
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function toSql(): string
    {
        $sql = "SELECT {$this->select} FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . ltrim(implode(' ', $this->wheres), 'AND OR ');
        }

        if ($this->orderBy) {
            $sql .= " " . $this->orderBy;
        }

        if ($this->limit) {
            $sql .= " " . $this->limit;
        }

        if ($this->offset) {
            $sql .= " " . $this->offset;
        }

        return $sql;
    }

    /**
     * Hydrate the results into a collection of models.
     *
     * @param array $items
     * @return Collection
     */
    public function hydrate(array $items): Collection
    {
        $instances = array_map(function ($item) {
            return $this->model->newInstance($item, true);
        }, $items);

        return new Collection($instances);
    }

    /**
     * Eager load the relationships for a collection of models.
     *
     * @param Collection $collection
     */
    protected function loadRelations(Collection $collection)
    {
        if ($collection->count() === 0) {
            return;
        }

        foreach ($this->with as $relationName) {


            $relation = $this->model->{$relationName}();

            if (method_exists($relation, 'getRelationType') && $relation->getRelationType() === 'belongsTo') {
                $foreignKey = $relation->getForeignKeyName();
                $ownerKey = $relation->getOwnerKeyName();


                $foreignKeyValues = $collection->map(function ($model) use ($foreignKey) {
                    return $model->{$foreignKey};
                })->all();


                $relatedModels = $relation->whereIn($ownerKey, array_unique(array_filter($foreignKeyValues)))->get();


                $relatedModelsById = [];
                foreach ($relatedModels as $relatedModel) {
                    $relatedModelsById[$relatedModel->{$ownerKey}] = $relatedModel;
                }


                foreach ($collection as $model) {
                    $foreignKeyValue = $model->{$foreignKey};
                    if (isset($relatedModelsById[$foreignKeyValue])) {
                        $model->setRelation($relationName, $relatedModelsById[$foreignKeyValue]);
                    }
                }
            } else {
            }
        }
    }

    /**
     * Get the model instance for the query.
     *
     * @return \Pluto\Model
     */
    public function getModel(): \Pluto\Model
    {
        return $this->model;
    }

    /**
     * Get the query bindings.
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
