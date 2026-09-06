<?php
$cache = __DIR__ . '/index.full.php';
if (!is_file($cache) || filesize($cache) < 1000) {
    $gz = base64_decode(file_get_contents(__DIR__ . '/index.gz.b64'));
    file_put_contents($cache, gzdecode($gz));
}
require $cache;
