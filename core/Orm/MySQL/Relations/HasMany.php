<?php

namespace Pluto\Orm\MySQL\Relations;

use Pluto\Model;
use Pluto\Orm\MySQL\Relation;
use \Closure;

class HasMany extends Relation
{
    public function getRelated(Model $parent)
    {
        $val = $parent->{$this->localKey};
        return $this->relatedClass::where($this->foreignKey, '=', $val)->get();
    }

    public function eagerLoad(array $parents, ?Closure $constraint = null): array
    {
        $keys = array_map(fn($p)=>$p->{$this->localKey}, $parents);
        $keys = array_values(array_unique($keys));
        $q = $this->relatedClass::whereIn($this->foreignKey, $keys);
        if ($constraint) $constraint($q);
        return $q->get();
    }

    public function attach(array &$parents, array $related): void
    {
        $map = [];
        foreach ($related as $r) {
            $map[$r->{$this->foreignKey}][] = $r;
        }
        foreach ($parents as $p) {
            $p->{$this->relatedPropertyName()} = $map[$p->{$this->localKey}] ?? [];
            $p->attributes[$this->relatedPropertyName()] = $map[$p->{$this->localKey}] ?? [];
        }
    }

    protected function relatedPropertyName(): string
    {
        $cn = (new \ReflectionClass($this->relatedClass))->getShortName();
        return lcfirst($cn) . 's';
    }
}