<?php
/**
 * Админка: при первом запросе подтягивает полный UI из GitHub (коммит с рабочей админкой)
 * и вставляет theme.js. Дальше работает из локального кэша admin_app.full.php
 */
$cache = __DIR__ . '/admin_app.full.php';

if (!is_file($cache) || filesize($cache) < 5000) {
    $urls = [
        'https://raw.githubusercontent.com/Gitromanus/1c-delivery-logistics/5f89d8ed0c0c0c0c/public/admin/index.php',
        'https://cdn.jsdelivr.net/gh/Gitromanus/1c-delivery-logistics@5f89d8e/public/admin/index.php',
    ];
    // точный SHA коммита с полной админкой
    $urls = [
        'https://raw.githubusercontent.com/Gitromanus/1c-delivery-logistics/5f89d8e/public/admin/index.php',
        'https://cdn.jsdelivr.net/gh/Gitromanus/1c-delivery-logistics@5f89d8e/public/admin/index.php',
    ];
    $data = false;
    foreach ($urls as $url) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 25,
                'header' => "User-Agent: 1c-logistics-admin\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
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
        echo "Не удалось загрузить admin UI. Залейте вручную public/admin/index.php из репозитория (коммит 5f89d8e).\n";
        echo "Или положите полный файл как admin_app.full.php в эту папку.";
        exit;
    }
    // тема
    $needle = '<link rel="stylesheet" href="../assets/css/style.css">';
    $inject = $needle . "\n  <script src=\"../assets/js/theme.js\"></script>";
    if (strpos($data, 'theme.js') === false) {
        $data = str_replace($needle, $inject, $data);
    }
    file_put_contents($cache, $data);
}

require $cache;
