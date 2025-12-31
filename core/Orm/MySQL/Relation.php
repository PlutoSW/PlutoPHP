<?php

namespace Pluto\Orm\MySQL;

use Pluto\Model;
use \Closure;

use Pluto\Orm\MySQL\Relations\HasOne;
use Pluto\Orm\MySQL\Relations\HasMany;
use Pluto\Orm\MySQL\Relations\BelongsTo;
use Pluto\Orm\MySQL\Relations\BelongsToMany;


abstract class Relation
{
    protected Model $parent;
    protected string $foreignKey;
    protected string $localKey;
    protected string $relatedTable;
    protected string $relatedClass;

    public function __construct(Model $parent, string $relatedClass, string $foreignKey, string $localKey)
    {
        $this->parent = $parent;
        $this->relatedClass = $relatedClass;
        $this->relatedTable = $relatedClass::$table ?: strtolower((new \ReflectionClass($relatedClass))->getShortName()) . 's';
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    abstract public function getRelated(Model $parent);
    abstract public function eagerLoad(array $parents, ?Closure $constraint = null): array;
    abstract public function attach(array &$parents, array $related): void;

    public function getRelatedTable(): string
    {
        return $this->relatedTable;
    }
    public function getRelatedClass(): string
    {
        return $this->relatedClass;
    }
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }
}

trait RelationsTrait
{
    public function hasOne(string $relatedClass, string $foreignKey, string $localKey = 'id')
    {
        return new HasOne($this, $relatedClass, $foreignKey, $localKey);
    }

    public function hasMany(string $relatedClass, string $foreignKey, string $localKey = 'id')
    {
        return new HasMany($this, $relatedClass, $foreignKey, $localKey);
    }

    public function belongsTo(string $relatedClass, string $foreignKey, string $ownerKey = 'id')
    {
        return new BelongsTo($this, $relatedClass, $foreignKey, $ownerKey);
    }

    public function belongsToMany(string $relatedClass, string $pivotTable, string $pivotLocalKey, string $pivotForeignKey)
    {
        return new BelongsToMany($this, $relatedClass, $pivotTable, $pivotLocalKey, $pivotForeignKey);
    }
}