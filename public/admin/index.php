<?php
require dirname(__DIR__) . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireAdmin('../login.php');
}
$app = __DIR__ . '/admin_app.full.php';
if (!is_file($app) || filesize($app) < 10000) {
    $p1 = __DIR__ . '/admin_p1.php';
    $p2 = __DIR__ . '/admin_p2.php';
    if (is_file($p1) && is_file($p2)) {
        $raw = file_get_contents($p1) . file_get_contents($p2);
        if (strlen($raw) > 10000) {
            file_put_contents($app, $raw);
        }
    }
    if ((!is_file($app) || filesize($app) < 10000)) {
        $b64 = '';
        for ($i = 0; $i <= 10; $i++) {
            $f = __DIR__ . '/admin_chunk_' . $i . '.php';
            if (!is_file($f)) break;
            $b64 .= (string) require $f;
        }
        if ($b64 !== '') {
            $gz = base64_decode($b64, true);
            $raw = ($gz !== false) ? @gzdecode($gz) : false;
            if ($raw !== false && strlen($raw) > 10000) {
                file_put_contents($app, $raw);
            }
        }
    }
}
if (!is_file($app) || filesize($app) < 1000) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Нужен admin/admin_app.full.php\n";
    exit;
}
require $app;
