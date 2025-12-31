<?php

namespace Pluto\Orm\MySQL\Relations;

use Pluto\Model;
use Pluto\Orm\MySQL\Relation;
use \Closure;

class BelongsTo extends Relation
{
    public function getRelated(Model $parent)
    {
        $val = $parent->{$this->foreignKey};
        return $this->relatedClass::where($this->localKey, '=', $val)->first();
    }

    public function eagerLoad(array $parents, ?Closure $constraint = null): array
    {
        $keys = array_map(fn($p)=>$p->{$this->foreignKey}, $parents);
        $keys = array_values(array_unique($keys));
        $q = $this->relatedClass::whereIn($this->localKey, $keys);
        if ($constraint) $constraint($q);
        return $q->get();
    }

    public function attach(array &$parents, array $related): void
    {
        $map = [];
        foreach ($related as $r) $map[$r->{$this->localKey}] = $r;
        foreach ($parents as $p) {
            $p->{$this->relatedPropertyName()} = $map[$p->{$this->foreignKey}] ?? null;
            $p->attributes[$this->relatedPropertyName()] = $map[$p->{$this->foreignKey}] ?? null;
        }
    }

    protected function relatedPropertyName(): string
    {
        $cn = (new \ReflectionClass($this->relatedClass))->getShortName();
        return lcfirst($cn);
    }
}
