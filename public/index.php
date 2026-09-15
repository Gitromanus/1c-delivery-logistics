<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireLogin('login.php');
}
require __DIR__ . '/desk_logic.php';

// HTML-часть: если есть desk_view.php — из него, иначе встроенный минимальный редирект
if (is_file(__DIR__ . '/desk_view.php')) {
    require __DIR__ . '/desk_view.php';
    exit;
}

// Fallback: старый index через desk_app
if (is_file(__DIR__ . '/desk_app.php')) {
    require __DIR__ . '/desk_app.php';
    exit;
}

http_response_code(500);
echo 'Залейте desk_view.php (HTML рабочего стола).';
