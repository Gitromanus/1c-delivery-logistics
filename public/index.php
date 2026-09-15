<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireLogin('login.php');
}
require __DIR__ . '/desk_logic.php';

// HTML/JS рабочего стола — локальные части (без GitHub)
$parts = [
    __DIR__ . '/desk_view_part1.php',
    __DIR__ . '/desk_view_part2.php',
    __DIR__ . '/desk_view_part3.php',
    __DIR__ . '/desk_view_part4.php',
];
$missing = [];
foreach ($parts as $p) {
    if (!is_file($p)) {
        $missing[] = basename($p);
    }
}
if ($missing) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Нет файлов: " . implode(', ', $missing) . "\nЗалейте public/desk_view_part1..4.php\n";
    exit;
}
foreach ($parts as $p) {
    require $p;
}
