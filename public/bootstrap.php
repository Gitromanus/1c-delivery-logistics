<?php

declare(strict_types=1);

$root = __DIR__;

$configPath = $root . '/config.php';
if (!is_file($configPath)) {
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
    echo "Не найдена папка src/. Залейте public/src/ в public_html/src/";
    exit;
}

require_once $srcDir . '/Database.php';
require_once $srcDir . '/ZoneMatcher.php';
require_once $srcDir . '/TripBuilder.php';
require_once $srcDir . '/Geocoder.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname($configPath));
}
