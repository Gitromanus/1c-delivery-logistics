<?php
$cache = __DIR__ . '/index.full.php';
if (!is_file($cache) || filesize($cache) < 1000) {
    $t = '';
    for ($i = 0; $i < 6; $i++) {
        $t .= file_get_contents(__DIR__ . "/part_$i.txt");
    }
    file_put_contents($cache, $t);
}
require $cache;
