<?php
$cache = __DIR__ . '/index.full.php';
if (!is_file($cache) || filesize($cache) < 1000) {
    $b = '';
    for ($i = 0; $i < 6; $i++) {
        $b .= file_get_contents(__DIR__ . "/index_b64_$i.txt");
    }
    file_put_contents($cache, base64_decode($b));
}
require $cache;
