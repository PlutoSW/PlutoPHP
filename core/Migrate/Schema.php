<?php

namespace Pluto\Migrate;

use Closure;
use Pluto\ORM\Mysql\Database;

class Schema
{
    public static function create(string $table, Closure $callback)
    {
        $blueprint = new Blueprint($table, false);
        $callback($blueprint);
        
        $sql = $blueprint->toSql();
        
        $pdo = Database::getInstance();
        $pdo->exec($sql);
    }

    public static function table(string $table, Closure $callback)
    {
        $blueprint = new Blueprint($table, true);
        $callback($blueprint);

        $sqls = $blueprint->toSql();

        $pdo = Database::getInstance();
        foreach (explode(';', $sqls) as $sql) {
            if (trim($sql)) {
                $pdo->exec($sql);
            }
        }
    }

    public static function drop(string $table)
    {
        self::dropIfExists($table);
    }

    public static function dropIfExists(string $table)
    {
        $pdo = Database::getInstance();
        $sql = "DROP TABLE IF EXISTS `{$table}`;";
        $pdo->exec($sql);
    }
}
