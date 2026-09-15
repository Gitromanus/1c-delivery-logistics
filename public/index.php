<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireLogin('login.php');
}
require __DIR__ . '/desk_logic.php';

$view = __DIR__ . '/desk_view.php';

if (!is_file($view) || filesize($view) < 10000) {
    $b64 = '';
    $ok = true;
    for ($i = 0; $i <= 1; $i++) {
        $f = __DIR__ . '/view_chunk_' . $i . '.php';
        if (!is_file($f)) { $ok = false; break; }
        $b64 .= (string) require $f;
    }
    if ($ok && $b64 !== '') {
        $gz = base64_decode($b64, true);
        $raw = $gz !== false ? @gzdecode($gz) : false;
        if ($raw !== false && strlen($raw) > 10000) {
            file_put_contents($view, $raw);
        }
    }
}

if (!is_file($view) || filesize($view) < 1000) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Нужен файл desk_view.php (или view_chunk_0.php + view_chunk_1.php).\n";
    exit;
}
require $view;
