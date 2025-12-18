<?php

namespace Pluto\Orm\MySQL;

use Pluto\Model;

class BelongsTo
{
    protected QueryBuilder $query;
    protected Model $parent;
    protected string $foreignKey;
    protected string $ownerKey;

    public function __construct(QueryBuilder $query, Model $parent, string $foreignKey, string $ownerKey)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    public function getRelationType(): string
    {
        return 'belongsTo';
    }

    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    public function getOwnerKeyName(): string
    {
        return $this->ownerKey;
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Dinamik olarak QueryBuilder metotlarını çağırmak için.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->query->{$method}(...$parameters);
    }
}