<?php
/**
 * Админка: кэш admin_app.full.php; при отсутствии — скачать из GitHub + theme.js
 */
$cache = __DIR__ . '/admin_app.full.php';

if (!is_file($cache) || filesize($cache) < 5000) {
    $sha = '5f89d8e49521798a55b9e2c052c6cfec02721b41';
    $urls = [
        "https://raw.githubusercontent.com/Gitromanus/1c-delivery-logistics/{$sha}/public/admin/index.php",
        "https://cdn.jsdelivr.net/gh/Gitromanus/1c-delivery-logistics@{$sha}/public/admin/index.php",
    ];
    $data = false;
    foreach ($urls as $url) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 25,
                'header' => "User-Agent: 1c-logistics-admin\r\n",
            ],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data !== false && strlen($data) > 5000) {
            break;
        }
        $data = false;
    }
    if ($data === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Не удалось загрузить admin UI с GitHub (нужен исходящий HTTPS с хостинга).\n";
        echo "Залейте вручную файл admin_app.full.php (полный index админки) в папку admin/.\n";
        exit;
    }
    $needle = '<link rel="stylesheet" href="../assets/css/style.css">';
    $inject = $needle . "\n  <script src=\"../assets/js/theme.js\"></script>";
    if (strpos($data, 'theme.js') === false) {
        $data = str_replace($needle, $inject, $data);
    }
    file_put_contents($cache, $data);
}

require $cache;
