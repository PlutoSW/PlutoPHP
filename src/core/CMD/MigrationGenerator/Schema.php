<?php

namespace Pluto\Core\CMD\MigrationGenerator;

use Pluto\Core\CMD\MigrationGenerator\Table;

class Schema
{
    public static $sqlString = "";
    public static $table;
    public static function create($name, $callback)
    {
        if (empty($name)) {
            throw new \InvalidArgumentException("Schema name cannot be empty.");
        }
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException("Callback must be a callable function.");
        }

        $name = trim($name);
        self::$table = $name;


        $callback(new Table(new self()));

        return new self();
    }

    public static function update($name, $callback)
    {
        if (empty($name)) {
            throw new \InvalidArgumentException("Schema name cannot be empty.");
        }
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException("Callback must be a callable function.");
        }

        $name = trim($name);
        self::$table = $name;

        $callback(new Table(new self(), 'update'));

        return new self();
    }

    public static function drop($name)
    {
        if (empty($name)) {
            throw new \InvalidArgumentException("Schema name cannot be empty.");
        }

        $name = trim($name);
        self::$sqlString = "DROP TABLE IF EXISTS `{$name}`;" . PHP_EOL;

        return new self();
    }
}
