<?php

namespace Pluto;

use JsonSerializable;
use PDO;

abstract class Model implements JsonSerializable
{
    /**
     * The table associated with the model.
     * @var string
     */
    protected static string $table;

    /**
     * The primary key for the model.
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     * @var bool
     */
    public bool $timestamps = true;

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected array $fillable = [];

    /**
     * The attributes that should be hidden for arrays.
     * @var array
     */
    protected array $hidden = [];

    /**
     * The attributes that should be visible in arrays.
     * @var array
     */
    protected array $visible = [];


    /**
     * The model's attributes.
     * @var array
     */
    protected array $attributes = [];

    /**
     * The model attribute's original state.
     * @var array
     */
    protected array $original = [];

    /**
     * Indicates if the model exists in the database.
     * @var bool
     */
    public bool $exists = false;


    /**
     * The model's relations.
     * @var array
     */
    protected array $relations = [];


    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param array $attributes
     * @return $this
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    /**
     * Create a new model instance and persist it to the database.
     *
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes = []): self
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Sync the original attributes with the current attributes.
     */
    public function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public static function getTableName(): string
    {
        if (isset(static::$table)) {
            return static::$table;
        }
        $class_name = explode('\\', static::class);
        return strtolower(end($class_name)) . 's';
    }

    /**
     * Get a new query builder for the model's table.
     *
     * @return \Pluto\Orm\MySQL\QueryBuilder
     */
    protected static function newQuery()
    {
        $driver = getenv('DB_CONNECTION') ?? 'mysql';

        $queryBuilderClass = match ($driver) {
            'mysql' => \Pluto\Orm\MySQL\QueryBuilder::class,
            default => throw new \Exception(__("errors.unsupported_db_driver", ['driver'=>$driver])),
        };
        return new $queryBuilderClass(static::class);
    }

    /**
     * Begin querying the model.
     *
     * @return \Pluto\Orm\MySQL\QueryBuilder
     */
    public static function query()
    {
        return static::newQuery();
    }

    /**
     * Save the model to the database.
     *
     * @return bool
     */
    public function save(): bool
    {
        $query = static::newQuery();
        $dirty = $this->getDirty();

        if ($this->exists) {
            if (count($dirty) > 0) {
                if ($this->timestamps) {
                    $this->updated_at = date('Y-m-d H:i:s');
                    $dirty['updated_at'] = $this->updated_at;
                }
                $result = $query->where($this->primaryKey, '=', $this->getKey())->update($dirty);
            } else {
                return true;
            }
        } else {
            if ($this->timestamps) {
                $now = date('Y-m-d H:i:s');
                if (!isset($this->created_at)) $this->created_at = $now;
                if (!isset($this->updated_at)) $this->updated_at = $now;
            }
            $result = $query->insert($this->attributes);
            if ($result) {
                $this->setAttribute($this->primaryKey, $query->lastInsertId());
                $this->exists = true;
            }
        }

        if ($result) {
            $this->syncOriginal();
        }

        return $result;
    }

    /**
     * Delete the model from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }
        $query = static::newQuery();
        $result = $query->where($this->primaryKey, '=', $this->getKey())->delete();
        if ($result) {
            $this->exists = false;
        }
        return $result;
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getAttribute($this->primaryKey);
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get an attribute from the model.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        return null;
    }

    /**
     * Get a relationship value from a method.
     *
     * @param  string  $method
     * @return mixed
     */
    protected function getRelationValue(string $method)
    {
        $relation = $this->{$method}();

        if (!$relation instanceof \Pluto\Orm\MySQL\QueryBuilder) {
            return null;
        }

        $isMany = str_ends_with(strtolower($method), 's');

        $result = $isMany ? $relation->get() : $relation->first();

        return $this->relations[$method] = $result;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed $value
     */
    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Set the given relationship on the model.
     *
     * @param  string  $relation
     * @param  mixed  $value
     */
    public function setRelation(string $relation, $value): void {
        $this->relations[$relation] = $value;
    }

    /**
     * Determine if an attribute is fillable.
     *
     * @param string $key
     * @return bool
     */
    protected function isFillable(string $key): bool
    {
        return in_array($key, $this->fillable);
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
     * @return array
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->newQuery()->{$method}(...$parameters);
    }

    /**
     * Handle dynamic static method calls into the model.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return (new static)->newQuery()->{$method}(...$parameters);
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $attributes = array_merge($this->attributes, $this->relationsToArray());

        if (!empty($this->visible)) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        } elseif (!empty($this->hidden)) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }

    /**
     * Get the model's relationships in array form.
     *
     * @return array
     */
    public function relationsToArray(): array
    {
        $attributes = [];
        foreach ($this->relations as $key => $relation) {
            $attributes[$key] = ($relation instanceof \Pluto\Orm\MySQL\Collection)
                ? $relation->toArray()
                : (method_exists($relation, 'toArray') ? $relation->toArray() : $relation);
        }
        return $attributes;
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }


    protected function hasMany(string $relatedModel, ?string $foreignKey = null, ?string $localKey = null)
    {
        $foreignKey = $foreignKey ?? strtolower(basename(str_replace('\\', '/', static::class))) . '_id';

        $localKey = $localKey ?? $this->primaryKey;
        
        $query = (new $relatedModel())->newQuery();

        return $this->exists ? $query->where($foreignKey, '=', $this->getAttribute($localKey)) : $query;
    }

    protected function belongsTo(string $relatedModel, ?string $foreignKey = null, ?string $ownerKey = null)
    {
        $foreignKey = $foreignKey ?? strtolower(basename(str_replace('\\', '/', $relatedModel))) . '_id';
        $ownerKey = $ownerKey ?? (new $relatedModel)->primaryKey;

        $relatedInstance = new $relatedModel();
        return $relatedInstance->newQuery()->where($ownerKey, '=', $this->getAttribute($foreignKey));
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = new static();
        $model->attributes = (array) $attributes;
        $model->exists = $exists;
        if ($exists) {
            $model->syncOriginal();
        }
        return $model;
    }

    public function getModel()
    {
        return $this;
    }
}
