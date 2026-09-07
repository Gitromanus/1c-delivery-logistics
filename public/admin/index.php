<?php
/**
 * Админка: полный UI в admin_app.full.php (кэш),
 * при отсутствии — скачать с GitHub и выполнить через require.
 * Важно: НЕ echo исходник — иначе PHP выведется текстом на страницу.
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
        echo "Не удалось загрузить admin UI с GitHub.\n";
        echo "Положите полный файл админки как admin/admin_app.full.php\n";
        exit;
    }
    file_put_contents($cache, $data);
}

// Подмешать скрипты в кэш-файл (один раз), затем require — PHP выполнится
$html = file_get_contents($cache);
$changed = false;

if (strpos($html, 'theme.js') === false) {
    $needle = '<link rel="stylesheet" href="../assets/css/style.css">';
    $inject = $needle . "\n  <script src=\"../assets/js/theme.js\"></script>";
    if (strpos($html, $needle) !== false) {
        $html = str_replace($needle, $inject, $html);
        $changed = true;
    }
}

if (strpos($html, 'admin-settings.js') === false) {
    if (stripos($html, '</body>') !== false) {
        $html = str_ireplace(
            '</body>',
            "  <script src=\"../assets/js/admin-settings.js?v=1\"></script>\n</body>",
            $html
        );
        $changed = true;
    } else {
        $html .= "\n<script src=\"../assets/js/admin-settings.js?v=1\"></script>\n";
        $changed = true;
    }
}

if ($changed) {
    file_put_contents($cache, $html);
}

require $cache;
