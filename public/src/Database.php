<?php

class Database
{
    /** @var PDO|null */
    private static $pdo;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $configFile = defined('APP_ROOT')
                ? APP_ROOT . '/config.php'
                : dirname(__DIR__) . '/config.php';
            $config = require $configFile;
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
