<?php
require dirname(__DIR__) . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireAdmin('../login.php');
}

$app = __DIR__ . '/admin_app.full.php';
if (!is_file($app) || filesize($app) < 10000) {
    $b64 = '';
    for ($i = 0; $i <= 4; $i++) {
        $f = __DIR__ . '/admin_chunk_' . $i . '.php';
        if (!is_file($f)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Нет admin_chunk_{$i}.php\n";
            exit;
        }
        $b64 .= (string) require $f;
    }
    $gz = base64_decode($b64, true);
    $raw = $gz !== false ? @gzdecode($gz) : false;
    if ($raw === false || strlen($raw) < 10000) {
        http_response_code(500);
        echo "Не удалось распаковать admin\n";
        exit;
    }
    file_put_contents($app, $raw);
}
require $app;
