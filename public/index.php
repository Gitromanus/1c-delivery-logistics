<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireLogin('login.php');
}
require __DIR__ . '/desk_logic.php';

$view = __DIR__ . '/desk_view.php';
if (!is_file($view) || filesize($view) < 2000) {
    $b64 = '';
    foreach (['desk_view_0.b64', 'desk_view_1.b64', 'desk_view_2.b64'] as $f) {
        $p = __DIR__ . '/' . $f;
        if (is_file($p)) {
            $b64 .= file_get_contents($p);
        }
    }
    if ($b64 !== '') {
        $decoded = base64_decode($b64, true);
        if ($decoded !== false) {
            file_put_contents($view, $decoded);
        }
    }
}

if (is_file($view)) {
    require $view;
    exit;
}

http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo "Нет desk_view.php. Залейте public/desk_view_*.b64 или desk_view.php\n";
