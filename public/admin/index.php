<?php
require dirname(__DIR__) . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireAdmin('../login.php');
}

$app = __DIR__ . '/admin_app.full.php';
$pack = __DIR__ . '/admin_b64.php';

if ((!is_file($app) || filesize($app) < 10000) && is_file($pack)) {
    $b64 = (string) require $pack;
    $gz = base64_decode(preg_replace('/\s+/', '', $b64), true);
    if ($gz !== false) {
        $raw = @gzdecode($gz);
        if ($raw !== false && strlen($raw) > 10000) {
            file_put_contents($app, $raw);
        }
    }
}

if (is_file($app) && filesize($app) > 1000) {
    require $app;
    exit;
}

http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo "Нет admin_app.full.php / admin_b64.php\n";
