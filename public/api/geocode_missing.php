<?php
/**
 * Разово проставить координаты у заявок без lat/lon (нужен yandex_maps_key).
 * Открой в браузере будучи залогиненным в admin ИЛИ с X-Api-Key.
 */
session_start();
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
$yandexKey = (string) ($config['yandex_maps_key'] ?? '');
$apiKey = (string) ($config['api_key'] ?? '');
$given = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (empty($_SESSION['admin_ok']) && !hash_equals($apiKey, (string) $given)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($yandexKey === '') {
    echo json_encode(['ok' => false, 'error' => 'yandex_maps_key пустой']);
    exit;
}

$pdo = Database::pdo();
$rows = $pdo->query(
    "SELECT id, address FROM orders WHERE (lat IS NULL OR lon IS NULL) AND address <> '' ORDER BY id DESC LIMIT 30"
)->fetchAll();

$upd = $pdo->prepare('UPDATE orders SET lat = ?, lon = ? WHERE id = ?');
$ok = 0;
$fail = 0;

foreach ($rows as $row) {
    $geo = Geocoder::geocode($row['address'], $yandexKey);
    if ($geo) {
        $upd->execute([$geo['lat'], $geo['lon'], $row['id']]);
        $ok++;
    } else {
        $fail++;
    }
    usleep(150000); // не долбить API
}

echo json_encode(['ok' => true, 'geocoded' => $ok, 'failed' => $fail, 'left_batch' => count($rows)], JSON_UNESCAPED_UNICODE);
