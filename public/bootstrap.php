<?php

declare(strict_types=1);

/**
 * На Beget обычно всё лежит в public_html:
 *   config.php, src/, index.php, api/, admin/
 * Локально/в репо тот же вариант: файлы из public/ + config.php рядом.
 */
$root = __DIR__;

$configPath = $root . '/config.php';
if (!is_file($configPath)) {
    // запасной путь: config на уровень выше public/
    $alt = dirname($root) . '/config.php';
    if (is_file($alt)) {
        $configPath = $alt;
        $root = dirname($root);
    }
}

if (!is_file($configPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Создайте config.php рядом с index.php (скопируйте config.example.php).";
    exit;
}

$config = require $configPath;
date_default_timezone_set($config['timezone'] ?? 'Europe/Moscow');

$srcDir = is_dir($root . '/src') ? $root . '/src' : __DIR__ . '/src';
if (!is_dir($srcDir)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Не найдена папка src/ (Database.php, TripBuilder.php). Залейте её в public_html/src/";
    exit;
}

require_once $srcDir . '/Database.php';
require_once $srcDir . '/ZoneMatcher.php';
require_once $srcDir . '/TripBuilder.php';

// Корень проекта для Database (где config.php)
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname($configPath));
}
