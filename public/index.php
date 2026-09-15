<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireLogin('login.php');
}
require __DIR__ . '/desk_logic.php';

$view = __DIR__ . '/desk_view.php';
$pack = __DIR__ . '/desk_view.gz.b64';

if ((!is_file($view) || filesize($view) < 10000) && is_file($pack)) {
    $b64 = preg_replace('/\s+/', '', (string) file_get_contents($pack));
    $gz = base64_decode($b64, true);
    if ($gz !== false) {
        $raw = @gzdecode($gz);
        if ($raw !== false && strlen($raw) > 10000) {
            file_put_contents($view, $raw);
        }
    }
}

if (!is_file($view) || filesize($view) < 1000) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Нет desk_view.php / desk_view.gz.b64\n";
    exit;
}
require $view;
