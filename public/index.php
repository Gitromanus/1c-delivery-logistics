<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireLogin('login.php');
}
require __DIR__ . '/desk_logic.php';

$view = __DIR__ . '/desk_view.php';

// Если view пустой/placeholder — подтянуть HTML с GitHub (исторический index)
if (!is_file($view) || filesize($view) < 5000) {
    $sha = '700f197c59412d07ec8f4fe119e45030d286b453';
    $urls = [
        "https://raw.githubusercontent.com/Gitromanus/1c-delivery-logistics/{$sha}/public/index.php",
        "https://cdn.jsdelivr.net/gh/Gitromanus/1c-delivery-logistics@{$sha}/public/index.php",
    ];
    $raw = false;
    foreach ($urls as $url) {
        $ctx = stream_context_create(['http' => ['timeout' => 30, 'header' => "User-Agent: logistics\r\n"]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw !== false && strlen($raw) > 5000) {
            break;
        }
        $raw = false;
    }
    if ($raw !== false) {
        $pos = strpos($raw, '<!DOCTYPE');
        if ($pos === false) {
            $pos = strpos($raw, '<html');
        }
        if ($pos !== false) {
            $html = substr($raw, $pos);
            // Кнопка выхода + скрыть админку для не-админов (подстановка в HTML)
            $html = str_replace(
                '<a class="btn btn-ghost" href="admin/">Админка</a>',
                '<?php if (!class_exists(\'Auth\') || Auth::isAdmin() || !empty($_SESSION[\'config_admin\'])): ?>'
                . '<a class="btn btn-ghost" href="admin/">Админка</a><?php endif; ?>'
                . '<a class="btn btn-ghost" href="logout.php">Выйти</a>',
                $html
            );
            $html = str_replace(
                'width=device-width, initial-scale=1',
                'width=device-width, initial-scale=1, viewport-fit=cover',
                $html
            );
            file_put_contents($view, "<?php /* auto desk_view */ ?>\n" . $html);
        }
    }
}

if (is_file($view) && filesize($view) > 1000) {
    require $view;
    exit;
}

http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo "Не удалось собрать desk_view.php. Проверьте доступ к GitHub raw.\n";
