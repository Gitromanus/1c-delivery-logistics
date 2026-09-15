<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireLogin('login.php');
}
require __DIR__ . '/desk_logic.php';

$viewFile = __DIR__ . '/desk_view.php';

// Собрать из локальных base64-частей (без GitHub)
if (!is_file($viewFile) || filesize($viewFile) < 5000) {
    $b64 = '';
    for ($i = 0; $i < 4; $i++) {
        $p = __DIR__ . '/desk_view_b64_' . $i . '.txt';
        if (!is_file($p)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Нет файла desk_view_b64_{$i}.txt — дождитесь деплоя всех частей.\n";
            exit;
        }
        $b64 .= file_get_contents($p);
    }
    $decoded = base64_decode($b64, true);
    if ($decoded === false || strlen($decoded) < 5000) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Ошибка сборки desk_view из base64.\n";
        exit;
    }
    file_put_contents($viewFile, $decoded);
}

require $viewFile;
