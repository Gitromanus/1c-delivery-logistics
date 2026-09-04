<?php
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

$pdo = Database::pdo();
$rows = $pdo->query(
    "SELECT id, address FROM orders WHERE (lat IS NULL OR lon IS NULL) AND address <> '' ORDER BY id DESC LIMIT 20"
)->fetchAll();

$upd = $pdo->prepare('UPDATE orders SET lat = ?, lon = ? WHERE id = ?');
$ok = 0;
$fail = 0;
$errors = [];
$providers = [];

foreach ($rows as $row) {
    $meta = Geocoder::geocodeWithMeta($row['address'], $yandexKey);
    if ($meta['point']) {
        $upd->execute([$meta['point']['lat'], $meta['point']['lon'], $row['id']]);
        $ok++;
        $providers[$meta['provider'] ?? '?'] = ($providers[$meta['provider'] ?? '?'] ?? 0) + 1;
    } else {
        $fail++;
        if (count($errors) < 8) {
            $errors[] = [
                'id' => (int) $row['id'],
                'address' => $row['address'],
                'error' => $meta['error'],
            ];
        }
    }
    // Nominatim просит не чаще ~1 запроса/сек
    usleep(1100000);
}

echo json_encode([
    'ok' => true,
    'geocoded' => $ok,
    'failed' => $fail,
    'left_batch' => count($rows),
    'providers' => $providers,
    'sample_errors' => $errors,
], JSON_UNESCAPED_UNICODE);
