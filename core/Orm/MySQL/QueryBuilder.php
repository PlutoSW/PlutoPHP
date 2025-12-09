<?php

namespace Pluto\Orm\MySQL;

use PDO;
use Pluto\Model;


class QueryBuilder
{
    protected PDO $pdo;
    protected string $table;
    protected Model $model;

    protected string $select = '*';
    protected array $wheres = [];
    protected array $bindings = [];
    protected ?string $orderBy = null;
    protected ?string $limit = null;
    protected ?string $offset = null;
    protected array $with = [];

    public function __construct(string $modelClass)
    {
        $this->pdo = Database::getInstance();
        $this->model = new $modelClass;
        $this->table = $this->model->getTableName();
    }

    public function where($column, $operator = null, $value = null): self
    {
        $boolean = empty($this->wheres) ? '' : 'AND ';
        $this->wheres[] = "{$boolean}`{$column}` {$operator} ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        $boolean = empty($this->wheres) ? '' : 'OR ';
        $this->wheres[] = "{$boolean}`{$column}` {$operator} ?";
        $this->bindings[] = $value;
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
        return $this->where('id', '=', $id)->first();
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

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function toSql(): string
    {
        $sql = "SELECT {$this->select} FROM {$this->table}";

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
            $relationInstance = $this->model->{$relationName}();
            $relatedModel = $relationInstance->getModel();


            $foreignKey = strtolower(basename(str_replace('\\', '/', get_class($this->model)))) . '_id';
            $localKey = $this->model->getKeyName();

            $ids = $collection->map(function ($model) use ($localKey) {
                return $model->{$localKey};
            })->all();

            $relatedModels = $relatedModel::query()->whereIn($foreignKey, $ids)->get();

            $grouped = [];
            foreach ($relatedModels as $relatedModel) {
                $grouped[$relatedModel->{$foreignKey}][] = $relatedModel;
            }

            foreach ($collection as $model) {
                $modelId = $model->{$localKey};
                if (isset($grouped[$modelId])) {
                    $model->setRelation($relationName, new Collection($grouped[$modelId]));
                } else {
                    $model->setRelation($relationName, new Collection());
                }
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
}
