<?php

namespace Pluto\Orm\MySQL\Relations;

use Pluto\Model;
use Pluto\Orm\MySQL\Database;
use Pluto\Orm\MySQL\Relation;
use \Closure;
use \PDO;

class BelongsToMany extends Relation
{
    protected string $pivotTable;
    protected string $pivotLocalKey;
    protected string $pivotForeignKey;

    public function __construct(Model $parent, string $relatedClass, string $pivotTable, string $pivotLocalKey, string $pivotForeignKey)
    {
        $this->parent = $parent;
        $this->relatedClass = $relatedClass;
        $this->relatedTable = $relatedClass::$table ?: strtolower((new \ReflectionClass($relatedClass))->getShortName()) . 's';
        $this->pivotTable = $pivotTable;
        $this->pivotLocalKey = $pivotLocalKey;
        $this->pivotForeignKey = $pivotForeignKey;
    }

    public function getRelated(Model $parent)
    {
        $pk = $parent->{$this->pivotLocalKey};
        $sql = "SELECT r.* FROM {$this->relatedTable} r JOIN {$this->pivotTable} p ON p.{$this->pivotForeignKey} = r.id WHERE p.{$this->pivotLocalKey} = :pk";
        $rows = Database::raw($sql, [':pk' => $pk]);
        return array_map(fn($r) => new $this->relatedClass($r), $rows);
    }

    public function eagerLoad(array $parents, ?Closure $constraint = null): array
    {
        $keys = array_map(fn($p) => $p->{$this->pivotLocalKey}, $parents);
        $keys = array_values(array_unique($keys));
        if (!$keys) return [];
        $in = implode(', ', array_map(fn($i) => '?', $keys));
        $sql = "SELECT p.{$this->pivotLocalKey} as _owner, r.* FROM {$this->pivotTable} p JOIN {$this->relatedTable} r ON p.{$this->pivotForeignKey} = r.id WHERE p.{$this->pivotLocalKey} IN ({$in})";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ret = [];
        foreach ($rows as $row) {
            $owner = $row['_owner'];
            unset($row['_owner']);
            $ret[$owner][] = new $this->relatedClass($row);
        }
        return $ret;
    }

    public function attach(array &$parents, array $related): void
    {
        // related is keyed by owner id
        foreach ($parents as $p) {
            $p->{$this->relatedPropertyName()} = $related[$p->{$this->pivotLocalKey}] ?? [];
            $p->attributes[$this->relatedPropertyName()] = $related[$p->{$this->pivotLocalKey}] ?? [];
        }
    }

    protected function relatedPropertyName(): string
    {
        $cn = (new \ReflectionClass($this->relatedClass))->getShortName();
        return lcfirst($cn) . 's';
    }
}
