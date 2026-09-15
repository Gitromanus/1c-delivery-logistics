<?php
require dirname(__DIR__) . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireAdmin('../login.php');
}
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
            'http' => ['timeout' => 25, 'header' => "User-Agent: 1c-logistics-admin\r\n"],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data !== false && strlen($data) > 5000) break;
        $data = false;
    }
    if ($data === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Не удалось загрузить admin UI.\n";
        exit;
    }
    file_put_contents($cache, $data);
}

$html = file_get_contents($cache);
$changed = false;

if (strpos($html, 'theme.js') === false) {
    $needle = '<link rel="stylesheet" href="../assets/css/style.css">';
    if (strpos($html, $needle) !== false) {
        $html = str_replace($needle, $needle . "\n  <script src=\"../assets/js/theme.js\"></script>", $html);
        $changed = true;
    }
}

$html2 = preg_replace('#\s*<script[^>]*admin-settings\.js[^>]*></script>#i', '', $html);
if ($html2 !== null && $html2 !== $html) {
    $html = $html2;
    $changed = true;
}
if (stripos($html, '</body>') !== false) {
    $html = str_ireplace('</body>', "  <script src=\"../assets/js/admin-settings.js?v=8\"></script>\n</body>", $html);
    $changed = true;
} else {
    $html .= "\n<script src=\"../assets/js/admin-settings.js?v=8\"></script>\n";
    $changed = true;
}

if (strpos($html, 'users.php') === false) {
    $nav = '<div class="admin-nav" style="margin:8px 0 16px;display:flex;gap:12px;flex-wrap:wrap">'
         . '<a href="users.php">Пользователи</a>'
         . '<a href="settings.php">Настройки API</a>'
         . '<a href="../index.php">Рабочий стол</a>'
         . '<a href="../logout.php">Выйти</a></div>';
    $html = preg_replace('#(<body[^>]*>)#i', '$1' . $nav, $html, 1);
    $changed = true;
}

if ($changed) {
    file_put_contents($cache, $html);
}
require $cache;
