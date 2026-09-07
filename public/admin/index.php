<?php
/**
 * Точка входа админки. Полный UI — admin_app.php (распаковывается из gzip при первом запросе).
 */
$full = __DIR__ . '/admin_app.php';
$cache = __DIR__ . '/admin_app.full.php';

if (!is_file($full) || filesize($full) < 5000) {
    $a = __DIR__ . '/admin_gz_a.b64';
    $b = __DIR__ . '/admin_gz_b.b64';
    if (is_file($a) && is_file($b)) {
        if (!is_file($cache) || filesize($cache) < 1000) {
            $bin = base64_decode(file_get_contents($a) . file_get_contents($b));
            $data = @gzdecode($bin);
            if ($data !== false) {
                file_put_contents($cache, $data);
            }
        }
        if (is_file($cache) && filesize($cache) > 1000) {
            require $cache;
            exit;
        }
    }
}

if (is_file($full)) {
    require $full;
    exit;
}

http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo "admin_app.php не найден. Залейте файлы admin_gz_a.b64 / admin_gz_b.b64 или полный admin_app.php";
