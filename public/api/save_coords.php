<?php
/**
 * Сохранить lat/lon, полученные в браузере через JS API (ymaps.geocode).
 */
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Expected {items:[{id,lat,lon}]}']);
    exit;
}

$pdo = Database::pdo();
$upd = $pdo->prepare('UPDATE orders SET lat = ?, lon = ? WHERE id = ? AND (lat IS NULL OR lon IS NULL)');
$n = 0;
foreach ($data['items'] as $item) {
    if (empty($item['id']) || !isset($item['lat'], $item['lon'])) {
        continue;
    }
    $upd->execute([(float) $item['lat'], (float) $item['lon'], (int) $item['id']]);
    $n += $upd->rowCount();
}

echo json_encode(['ok' => true, 'saved' => $n], JSON_UNESCAPED_UNICODE);
