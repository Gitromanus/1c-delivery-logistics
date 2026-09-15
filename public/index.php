<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireLogin('login.php');
}
require __DIR__ . '/desk_logic.php';

$view = __DIR__ . '/desk_view.php';

if (!is_file($view) || filesize($view) < 10000) {
    $b64 = '';
    foreach (['desk_view_b64_0.txt', 'desk_view_b64_1.txt'] as $f) {
        $p = __DIR__ . '/' . $f;
        if (!is_file($p)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Нет $f — дождитесь деплоя.\n";
            exit;
        }
        $b64 .= preg_replace('/\s+/', '', file_get_contents($p));
    }
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
