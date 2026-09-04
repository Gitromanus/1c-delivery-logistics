<?php

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
$apiKey = (string) ($config['api_key'] ?? '');
$yandexKey = (string) ($config['yandex_geocoder_key'] ?? ($config['yandex_maps_key'] ?? ''));

$given = $_SERVER['HTTP_X_API_KEY'] ?? ($_POST['api_key'] ?? '');
if ($apiKey === '' || !hash_equals($apiKey, (string) $given)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$items = [];
if (isset($data['orders']) && is_array($data['orders'])) {
    $items = $data['orders'];
} elseif (isset($data['external_id'])) {
    $items = [$data];
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Expected order object or {orders:[]}']);
    exit;
}

$pdo = Database::pdo();
$upsert = $pdo->prepare(
    "INSERT INTO orders (external_id, number, doc_date, partner, address, lat, lon, weight_kg, amount, comment, zone_id, status)
     VALUES (:external_id, :number, :doc_date, :partner, :address, :lat, :lon, :weight_kg, :amount, :comment, :zone_id, 'new')
     ON DUPLICATE KEY UPDATE
       number = VALUES(number),
       doc_date = VALUES(doc_date),
       partner = VALUES(partner),
       address = VALUES(address),
       lat = COALESCE(VALUES(lat), lat),
       lon = COALESCE(VALUES(lon), lon),
       weight_kg = VALUES(weight_kg),
       amount = VALUES(amount),
       comment = VALUES(comment),
       zone_id = VALUES(zone_id),
       updated_at = CURRENT_TIMESTAMP"
);

$saved = 0;
$errors = [];

foreach ($items as $i => $row) {
    if (!is_array($row) || empty($row['external_id']) || empty($row['address'])) {
        $errors[] = "Item $i: external_id and address required";
        continue;
    }
    $address = trim((string) $row['address']);
    $zoneId = ZoneMatcher::matchZoneId($pdo, $address);

    $lat = isset($row['lat']) ? (float) $row['lat'] : null;
    $lon = isset($row['lon']) ? (float) $row['lon'] : null;
    if ($lat === null && $lon === null && $yandexKey !== '') {
        $geo = Geocoder::geocode($address, $yandexKey);
        if ($geo) {
            $lat = $geo['lat'];
            $lon = $geo['lon'];
        }
    }

    try {
        $upsert->execute([
            ':external_id' => (string) $row['external_id'],
            ':number' => isset($row['number']) ? (string) $row['number'] : null,
            ':doc_date' => !empty($row['doc_date']) ? (string) $row['doc_date'] : date('Y-m-d'),
            ':partner' => isset($row['partner']) ? (string) $row['partner'] : null,
            ':address' => $address,
            ':lat' => $lat,
            ':lon' => $lon,
            ':weight_kg' => isset($row['weight_kg']) ? (float) $row['weight_kg'] : 0,
            ':amount' => isset($row['amount']) ? (float) $row['amount'] : null,
            ':comment' => isset($row['comment']) ? (string) $row['comment'] : null,
            ':zone_id' => $zoneId,
        ]);
        $saved++;
    } catch (Throwable $e) {
        $errors[] = "Item $i: " . $e->getMessage();
    }
}

echo json_encode([
    'ok' => empty($errors),
    'saved' => $saved,
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE);
