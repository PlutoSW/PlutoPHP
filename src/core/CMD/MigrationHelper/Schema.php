<?php

namespace Pluto\Core\CMD\MigrationHelper;

use Pluto\Core\CMD\MigrationHelper\Table;
use Pluto\Core\System;

System::init();


function getDatabaseConnection()
{
    $host = getenv('DB_IP');
    $user = getenv('DB_USER');
    $password = getenv('DB_PASS');
    $dbName = getenv('DB_NAME');

    $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
    try {
        return new \PDO($dsn, $user, $password);
    } catch (\PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

function runSqlQuery($sql)
{
    $pdo = getDatabaseConnection();
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        // close the connection
        $pdo = null;
        return $stmt;
    } catch (\PDOException $e) {
        die($sql);
    }
}

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

        runSqlQuery(self::$sqlString);
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
        runSqlQuery(self::$sqlString);
        return new self();
    }

    public static function drop($name)
    {
        if (empty($name)) {
            throw new \InvalidArgumentException("Schema name cannot be empty.");
        }

        $name = trim($name);
        self::$sqlString = "DROP TABLE IF EXISTS `{$name}`;" . PHP_EOL;
        runSqlQuery(self::$sqlString);
        return new self();
    }
}
