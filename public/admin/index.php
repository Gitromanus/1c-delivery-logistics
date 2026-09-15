<?php
require dirname(__DIR__) . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireAdmin('../login.php');
}

$app = __DIR__ . '/admin_app.full.php';
$pack = __DIR__ . '/admin.gz.b64';

if ((!is_file($app) || filesize($app) < 10000) && is_file($pack)) {
    $b64 = preg_replace('/\s+/', '', (string) file_get_contents($pack));
    $gz = base64_decode($b64, true);
    if ($gz !== false) {
        $raw = @gzdecode($gz);
        if ($raw !== false && strlen($raw) > 10000) {
            // strip leading <?php require bootstrap if present to avoid double
            if (strpos($raw, 'require dirname(__DIR__)') !== false && strpos($raw, 'Auth::requireAdmin') !== false) {
                // full file already has auth
                file_put_contents($app, $raw);
            } else {
                file_put_contents($app, $raw);
            }
        }
    }
}

if (is_file($app) && filesize($app) > 1000) {
    // admin_app already includes bootstrap/auth when restored
    // If it starts with require bootstrap again, include only once
    require $app;
    exit;
}

http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo "Нет admin_app.full.php / admin.gz.b64\n";
