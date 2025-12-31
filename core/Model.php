<?php

namespace Pluto;

use Pluto\Orm\MySQL\Database;
use Pluto\Orm\MySQL\QueryBuilder;
use Pluto\Orm\MySQL\Relation;


class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected array $attributes = [];
    protected array $original = [];
    protected array $fillable = [];
    protected bool $timestamps = true;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->original = $attributes;

    }

    public static function query(): QueryBuilder
    {
        $table = static::$table ?: strtolower((new \ReflectionClass(static::class))->getShortName()) . 's';
        $q = QueryBuilder::table($table)->model(static::class);
        return $q;
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find($id): ?self
    {
        $pk = static::$primaryKey;
        return static::query()->where($pk, '=', $id)->first();
    }

    public static function where($column, $op = null, $value = null): QueryBuilder
    {
        return static::query()->where($column, $op, $value);
    }

    public static function create(array $data): self
    {
        $m = new static($data);
        static::query()->insert($data);
        $m->attributes[static::$primaryKey] = Database::pdo()->lastInsertId();
        return $m;
    }

    public function save(): bool
    {
        $pk = static::$primaryKey;

        if (isset($this->attributes[$pk])) {
            $dirty = array_diff_assoc($this->attributes, $this->original);
            if (!$dirty) return true;

            static::query()
                ->where($pk, '=', $this->attributes[$pk])
                ->update($dirty);

            $this->original = $this->attributes;
            return true;
        }

        $id = static::query()->insertGetId($this->attributes);
        $this->attributes[$pk] = $id;
        $this->original = $this->attributes;
        return true;
    }

    public function delete(): bool
    {
        $pk = static::$primaryKey;
        if (!isset($this->attributes[$pk])) return false;
        $count = static::query()->where($pk, '=', $this->attributes[$pk])->delete();
        return $count > 0;
    }

    public function __get($k)
    {
        if (array_key_exists($k, $this->attributes)) return $this->attributes[$k];
        if (method_exists($this, $k)) {
            $rel = $this->$k();
            if ($rel instanceof Relation) return $rel->getRelated($this);
        }
        return null;
    }

    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
