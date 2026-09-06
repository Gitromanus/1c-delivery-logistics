<?php

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
// api_key или ApiKey1s (как назвали в config)
$apiKey = (string) ($config['api_key'] ?? $config['ApiKey1s'] ?? '');
$yandexKey = (string) ($config['yandex_geocoder_key'] ?? ($config['yandex_maps_key'] ?? ''));
$dadataToken = (string) ($config['dadata_token'] ?? '');

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

// lat/lon могут отсутствовать, если миграцию не накатывали — пишем без них
$hasCoords = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM orders LIKE 'lat'")->fetch();
    $hasCoords = (bool) $cols;
} catch (Throwable $e) {
    $hasCoords = false;
}

if ($hasCoords) {
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
} else {
    $upsert = $pdo->prepare(
        "INSERT INTO orders (external_id, number, doc_date, partner, address, weight_kg, amount, comment, zone_id, status)
         VALUES (:external_id, :number, :doc_date, :partner, :address, :weight_kg, :amount, :comment, :zone_id, 'new')
         ON DUPLICATE KEY UPDATE
           number = VALUES(number),
           doc_date = VALUES(doc_date),
           partner = VALUES(partner),
           address = VALUES(address),
           weight_kg = VALUES(weight_kg),
           amount = VALUES(amount),
           comment = VALUES(comment),
           zone_id = VALUES(zone_id),
           updated_at = CURRENT_TIMESTAMP"
    );
}

$saved = 0;
$errors = [];

foreach ($items as $i => $row) {
    if (!is_array($row) || empty($row['external_id']) || empty($row['address'])) {
        $errors[] = "Item $i: external_id and address required";
        continue;
    }
    $address = trim((string) $row['address']);
    if (mb_strlen($address) > 500) {
        $address = mb_substr($address, 0, 500);
    }

    $lat = isset($row['lat']) ? (float) $row['lat'] : null;
    $lon = isset($row['lon']) ? (float) $row['lon'] : null;
    $zoneId = null;

    if ($hasCoords) {
        if ($lat === null && $lon === null && class_exists('Geocoder')) {
            $geo = Geocoder::geocode($address, $yandexKey, $dadataToken);
            if ($geo) {
                $lat = $geo['lat'];
                $lon = $geo['lon'];
            }
        }
        if (class_exists('ZoneMatcher') && method_exists('ZoneMatcher', 'matchByCoords')) {
            $zoneId = ZoneMatcher::matchByCoords($pdo, $lat, $lon);
        }
        if ($zoneId === null && class_exists('ZoneMatcher') && method_exists('ZoneMatcher', 'matchZoneId')) {
            $zoneId = ZoneMatcher::matchZoneId($pdo, $address);
        }
    } else {
        if (class_exists('ZoneMatcher') && method_exists('ZoneMatcher', 'matchZoneId')) {
            $zoneId = ZoneMatcher::matchZoneId($pdo, $address);
        }
    }

    try {
        $params = [
            ':external_id' => mb_substr((string) $row['external_id'], 0, 100),
            ':number' => isset($row['number']) ? mb_substr((string) $row['number'], 0, 50) : null,
            ':doc_date' => !empty($row['doc_date']) ? (string) $row['doc_date'] : date('Y-m-d'),
            ':partner' => isset($row['partner']) ? mb_substr((string) $row['partner'], 0, 255) : null,
            ':address' => $address,
            ':weight_kg' => isset($row['weight_kg']) ? (float) $row['weight_kg'] : 0,
            ':amount' => isset($row['amount']) ? (float) $row['amount'] : null,
            ':comment' => isset($row['comment']) ? mb_substr((string) $row['comment'], 0, 500) : null,
            ':zone_id' => $zoneId,
        ];
        if ($hasCoords) {
            $params[':lat'] = $lat;
            $params[':lon'] = $lon;
        }
        $upsert->execute($params);
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
