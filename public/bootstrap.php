<?php

declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Создайте config.php из config.example.php";
    exit;
}

$config = require $configPath;
date_default_timezone_set($config['timezone'] ?? 'Europe/Moscow');

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/ZoneMatcher.php';
require_once dirname(__DIR__) . '/src/TripBuilder.php';
