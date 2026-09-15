<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireLogin('login.php');
}
require __DIR__ . '/desk_logic.php';

$view = __DIR__ . '/desk_view.php';
if (!is_file($view) || filesize($view) < 10000) {
    $b64 = '';
    for ($i = 0; $i <= 6; $i++) {
        $f = __DIR__ . '/view_chunk_' . $i . '.php';
        if (!is_file($f)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Нет view_chunk_{$i}.php\n";
            exit;
        }
        $b64 .= (string) require $f;
    }
    $gz = base64_decode($b64, true);
    $raw = $gz !== false ? @gzdecode($gz) : false;
    if ($raw === false || strlen($raw) < 10000) {
        http_response_code(500);
        echo "Не удалось распаковать desk_view\n";
        exit;
    }
    file_put_contents($view, $raw);
}
require $view;
