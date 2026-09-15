<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireLogin('login.php');
}
require __DIR__ . '/desk_logic.php';

$viewFile = __DIR__ . '/desk_view.php';
$pack = __DIR__ . '/desk_view.gz.b64';

if ((!is_file($viewFile) || filesize($viewFile) < 5000) && is_file($pack)) {
    $b64 = preg_replace('/\s+/', '', file_get_contents($pack));
    $gz = base64_decode($b64, true);
    if ($gz !== false) {
        $raw = @gzdecode($gz);
        if ($raw !== false && strlen($raw) > 5000) {
            file_put_contents($viewFile, $raw);
        }
    }
}

if (is_file($viewFile) && filesize($viewFile) > 5000) {
    require $viewFile;
    exit;
}

http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo "Нет desk_view.php / desk_view.gz.b64. Дождитесь деплоя или залейте desk_view.php вручную.\n";
