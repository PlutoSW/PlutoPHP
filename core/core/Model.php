<?php

namespace Pluto;

use Pluto\Orm\MySQL\Database;
use Pluto\Orm\MySQL\QueryBuilder;
use Pluto\Orm\MySQL\Relation;
use JsonSerializable;


class Model implements JsonSerializable
{
    public static string $table = '';
    protected static string $primaryKey = 'id';
    protected array $attributes = [];
    protected array $original = [];
    protected bool $timestamps = true;
    protected array $format = [];

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
            else return $rel;
        }
        return null;
    }

    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function toArray(): array
    {
        $attributes = $this->attributes;
        foreach ($this->format as $key => $formatter) {
            if (isset($attributes[$key]) && !is_null($attributes[$key])) {
                $attributes[$key] = $this->castAttribute($attributes[$key], $formatter);
            }
        }
        return $attributes;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    protected function castAttribute($value, $formatter)
    {
        if (is_string($formatter)) {
            $type = $formatter;
            $options = null;
        } elseif (is_array($formatter)) {
            $type = array_key_first($formatter);
            $options = $formatter[$type];
        } else {
            return $value;
        }

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'date' => date($options ?? 'Y-m-d', strtotime($value)),
            'datetime' => date($options ?? 'Y-m-d H:i:s', strtotime($value)),
            'json' => json_decode($value, true) ?? [],
            'decimal' => number_format((float)$value, $options['decimals'] ?? 2, $options['dec_point'] ?? '.', $options['thousands_sep'] ?? ''),
            'price' => number_format((float)$value, 2, ',', '.') . ' ' . ($options['currency'] ?? '₺'),
            default => $value,
        };
    }

    public function hasOne($related, $foreignKey = null, $localKey = 'id')
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        return new \Pluto\Orm\MySQL\Relations\HasOne($this, $related, $foreignKey, $localKey);
    }

    public function hasMany($related, $foreignKey = null, $localKey = 'id')
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        return new \Pluto\Orm\MySQL\Relations\HasMany($this, $related, $foreignKey, $localKey);
    }

    public function belongsTo($related, $foreignKey = null, $localKey = 'id')
    {
        $foreignKey = $foreignKey ?: $this->getForeignKeyFromClass($related);
        return new \Pluto\Orm\MySQL\Relations\BelongsTo($this, $related, $foreignKey, $localKey);
    }

    public function belongsToMany($related, $pivotTable, $pivotLocalKey, $pivotForeignKey)
    {
        return new \Pluto\Orm\MySQL\Relations\BelongsToMany($this, $related, $pivotTable, $pivotLocalKey, $pivotForeignKey);
    }

    protected function getForeignKey()
    {
        return strtolower((new \ReflectionClass($this))->getShortName()) . '_id';
    }

    protected function getForeignKeyFromClass($class)
    {
        return strtolower((new \ReflectionClass($class))->getShortName()) . '_id';
    }
}
