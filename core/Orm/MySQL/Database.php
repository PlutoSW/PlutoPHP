<?php

namespace Pluto\Orm\MySQL;

use \PDO;
use \PDOException;


/**
 * Database - PDO wrapper
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $config): void
    {
        if (self::$pdo) return;
        $dsn = $config['dsn'] ?? sprintf('%s:host=%s;dbname=%s;charset=%s', $config['driver'] ?? 'mysql', $config['host'] ?? '127.0.0.1', $config['database'] ?? '', $config['charset'] ?? 'utf8mb4');
        $user = $config['username'] ?? $config['user'] ?? null;
        $pass = $config['password'] ?? null;
        $options = $config['options'] ?? [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
        self::$pdo = new PDO($dsn, $user, $pass, $options);
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) throw new PDOException('Database not connected. Call Database::connect($config)');
        return self::$pdo;
    }

    public static function beginTransaction(): bool
    {
        return self::pdo()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::pdo()->commit();
    }

    public static function rollBack(): bool
    {
        return self::pdo()->rollBack();
    }

    public static function raw(string $sql, array $bindings = [])
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}