<?php
$cache = __DIR__ . '/index.full.php';
if (!is_file($cache) || filesize($cache) < 1000) {
    $b = file_get_contents(__DIR__ . '/index.gz_a.b64') . file_get_contents(__DIR__ . '/index.gz_b.b64');
    file_put_contents($cache, gzdecode(base64_decode($b)));
}
require $cache;
