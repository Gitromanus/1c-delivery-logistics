<?php

/** Настройки из app_settings + config.php (БД перекрывает файл). */
class Settings
{
    public static function get(string $key, $default = null)
    {
        try {
            $pdo = Database::pdo();
            $st = $pdo->prepare('SELECT svalue FROM app_settings WHERE skey = ? LIMIT 1');
            $st->execute([$key]);
            $v = $st->fetchColumn();
            if ($v !== false && $v !== null) {
                return $v;
            }
        } catch (Throwable $e) {
        }
        $configPath = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
        if (is_file($configPath)) {
            $cfg = require $configPath;
            if (isset($cfg[$key])) {
                return $cfg[$key];
            }
        }
        return $default;
    }

    public static function set(string $key, string $value): bool
    {
        try {
            $pdo = Database::pdo();
            $pdo->prepare(
                'INSERT INTO app_settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
            )->execute([$key, $value]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function apiKey(): string
    {
        $k = (string) self::get('api_key', '');
        if ($k !== '') {
            return $k;
        }
        $configPath = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
        if (is_file($configPath)) {
            $cfg = require $configPath;
            return (string) ($cfg['api_key'] ?? $cfg['ApiKey1s'] ?? '');
        }
        return '';
    }
}
