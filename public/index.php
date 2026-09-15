<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireLogin('login.php');
}
require __DIR__ . '/desk_logic.php';

$view = __DIR__ . '/desk_view.php';

if (!is_file($view) || filesize($view) < 10000) {
    $p0 = __DIR__ . '/desk_view_b64_0.php';
    $p1 = __DIR__ . '/desk_view_b64_1.php';
    if (!is_file($p0) || !is_file($p1)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Нет desk_view_b64_0.php / desk_view_b64_1.php\n";
        exit;
    }
    $b64 = (string) require $p0;
    $b64 .= (string) require $p1;
    $gz = base64_decode($b64, true);
    if ($gz === false) {
        http_response_code(500);
        echo "base64 error\n";
        exit;
    }
    $raw = @gzdecode($gz);
    if ($raw === false || strlen($raw) < 10000) {
        http_response_code(500);
        echo "gzdecode error\n";
        exit;
    }
    file_put_contents($view, $raw);
}

require $view;
