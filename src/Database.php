<?php

class Database
{
    /** @var PDO|null */
    private static $pdo;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $config = require dirname(__DIR__) . '/config.php';
            $db = $config['db'];
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $db['host'],
                $db['name'],
                $db['charset'] ?? 'utf8mb4'
            );
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }
}
