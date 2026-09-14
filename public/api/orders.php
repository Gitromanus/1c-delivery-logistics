<?php
/**
 * Приём заявок из 1С.
 * assign_trip: false / action: unassign → удаление из trip_items + status=new
 */
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
$apiKey = (string) ($config['api_key'] ?? $config['ApiKey1s'] ?? '');
$yandexKey = (string) ($config['yandex_geocoder_key'] ?? ($config['yandex_maps_key'] ?? $config['api_key_yandex'] ?? ''));
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
         VALUES (:external_id, :number, :doc_date, :partner, :address, :lat, :lon, :weight_kg, :amount, :comment, :zone_id, :status)
         ON DUPLICATE KEY UPDATE
           number = COALESCE(VALUES(number), number),
           doc_date = COALESCE(VALUES(doc_date), doc_date),
           partner = COALESCE(VALUES(partner), partner),
           address = IF(VALUES(address) IN ('', '—'), address, VALUES(address)),
           lat = COALESCE(VALUES(lat), lat),
           lon = COALESCE(VALUES(lon), lon),
           weight_kg = VALUES(weight_kg),
           amount = COALESCE(VALUES(amount), amount),
           comment = COALESCE(VALUES(comment), comment),
           zone_id = COALESCE(VALUES(zone_id), zone_id),
           status = VALUES(status),
           updated_at = CURRENT_TIMESTAMP"
    );
} else {
    $upsert = $pdo->prepare(
        "INSERT INTO orders (external_id, number, doc_date, partner, address, weight_kg, amount, comment, zone_id, status)
         VALUES (:external_id, :number, :doc_date, :partner, :address, :weight_kg, :amount, :comment, :zone_id, :status)
         ON DUPLICATE KEY UPDATE
           number = COALESCE(VALUES(number), number),
           doc_date = COALESCE(VALUES(doc_date), doc_date),
           partner = COALESCE(VALUES(partner), partner),
           address = IF(VALUES(address) IN ('', '—'), address, VALUES(address)),
           weight_kg = VALUES(weight_kg),
           amount = COALESCE(VALUES(amount), amount),
           comment = COALESCE(VALUES(comment), comment),
           zone_id = COALESCE(VALUES(zone_id), zone_id),
           status = VALUES(status),
           updated_at = CURRENT_TIMESTAMP"
    );
}

$saved = 0;
$errors = [];
$details = [];
$anyOverload = false;

foreach ($items as $i => $row) {
    if (!is_array($row) || empty($row['external_id'])) {
        $errors[] = "Item $i: external_id required";
        continue;
    }

    $externalId = mb_substr(trim((string) $row['external_id']), 0, 100);
    $action = strtolower(trim((string) ($row['action'] ?? '')));
    if ($action === '' && isset($data['action'])) {
        $action = strtolower(trim((string) $data['action']));
    }

    // assign_trip: по умолчанию true; false / 0 / "false" → не ставить в рейс
    $assignTrip = true;
    if (array_key_exists('assign_trip', $row)) {
        $v = $row['assign_trip'];
        if ($v === false || $v === 0 || $v === '0' || $v === 'false' || $v === 'False' || $v === 'FALSE') {
            $assignTrip = false;
        } elseif ($v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'True') {
            $assignTrip = true;
        } else {
            $assignTrip = (bool) $v;
        }
    }
    if ($action === 'unassign') {
        $assignTrip = false;
    }

    $doUnassign = ($action === 'unassign' || !$assignTrip);

    $address = trim((string) ($row['address'] ?? ''));
    if ($address === '' && !$doUnassign) {
        $errors[] = "Item $i: address required";
        continue;
    }
    if (mb_strlen($address) > 500) {
        $address = mb_substr($address, 0, 500);
    }

    $lat = isset($row['lat']) && $row['lat'] !== '' && $row['lat'] !== null ? (float) $row['lat'] : null;
    $lon = isset($row['lon']) && $row['lon'] !== '' && $row['lon'] !== null ? (float) $row['lon'] : null;
    $zoneId = isset($row['zone_id']) ? (int) $row['zone_id'] : null;
    if ($zoneId !== null && $zoneId <= 0) {
        $zoneId = null;
    }

    $geoProvider = null;
    $geoError = null;
    $docDate = !empty($row['doc_date']) ? (string) $row['doc_date'] : date('Y-m-d');
    $weightKg = isset($row['weight_kg']) ? (float) $row['weight_kg'] : 0;

    // --- Быстрый путь: только снять с рейса ---
    if ($action === 'unassign' && class_exists('OrderAssign')) {
        try {
            $assign = OrderAssign::unassignByExternalId($pdo, $externalId);
            // если заявки ещё нет — создадим минимальную запись
            if (($assign['message'] ?? '') === 'Заявка не найдена на сайте' && $address !== '') {
                // fall through to full upsert below
            } else {
                $saved++;
                $details[] = array_merge([
                    'external_id' => $externalId,
                    'action' => 'unassign',
                    'unassigned' => true,
                ], $assign);
                continue;
            }
        } catch (Throwable $e) {
            $errors[] = "Item $i: " . $e->getMessage();
            continue;
        }
    }

    if ($hasCoords && $lat === null && $lon === null && $address !== '') {
        $meta = Geocoder::geocodeWithMeta($address, $yandexKey, $dadataToken);
        if (!empty($meta['point'])) {
            $lat = (float) $meta['point']['lat'];
            $lon = (float) $meta['point']['lon'];
            $geoProvider = $meta['provider'] ?? 'ok';
        } else {
            $geoError = $meta['error'] ?? 'geocode failed';
        }
    }

    if ($zoneId === null && $lat !== null && $lon !== null) {
        $zoneId = ZoneMatcher::matchByCoords($pdo, $lat, $lon);
    }
    if ($zoneId === null && $address !== '') {
        $zoneId = ZoneMatcher::matchZoneId($pdo, $address);
    }

    try {
        $params = [
            ':external_id' => $externalId,
            ':number' => isset($row['number']) ? mb_substr((string) $row['number'], 0, 50) : null,
            ':doc_date' => $docDate,
            ':partner' => isset($row['partner']) ? mb_substr((string) $row['partner'], 0, 255) : null,
            ':address' => $address !== '' ? $address : '—',
            ':weight_kg' => $weightKg,
            ':amount' => isset($row['amount']) ? (float) $row['amount'] : null,
            ':comment' => isset($row['comment']) ? mb_substr((string) $row['comment'], 0, 500) : null,
            ':zone_id' => $zoneId,
            ':status' => 'new',
        ];
        if ($hasCoords) {
            $params[':lat'] = $lat;
            $params[':lon'] = $lon;
        }

        $upsert->execute($params);
        $saved++;

        $idStmt = $pdo->prepare('SELECT id FROM orders WHERE external_id = ? LIMIT 1');
        $idStmt->execute([$externalId]);
        $orderId = (int) $idStmt->fetchColumn();

        $assign = [
            'trip_id' => null,
            'vehicle_id' => null,
            'zone_id' => $zoneId,
            'zone_name' => null,
            'vehicle_name' => null,
            'vehicle_plate' => null,
            'capacity_kg' => 0,
            'loaded_kg' => 0,
            'free_kg' => 0,
            'overload' => false,
            'overload_kg' => 0,
            'load_percent' => 0,
            'message' => '',
            'unassigned' => true,
            'deleted_links' => 0,
        ];

        if ($orderId > 0 && class_exists('OrderAssign')) {
            if ($doUnassign) {
                // Явно снимаем с рейса (и если уже стояла)
                $assign = OrderAssign::unassign($pdo, $orderId);
                if ($zoneId) {
                    $pdo->prepare('UPDATE orders SET zone_id = COALESCE(zone_id, ?) WHERE id = ?')
                        ->execute([$zoneId, $orderId]);
                    $assign['zone_id'] = $zoneId;
                }
            } elseif ($zoneId) {
                $assign = OrderAssign::toTrip($pdo, $orderId, $zoneId, $docDate, $weightKg);
            } else {
                $assign['message'] = 'Сохранена без зоны и рейса';
            }
        }

        if (!empty($assign['overload'])) {
            $anyOverload = true;
        }

        $details[] = [
            'external_id' => $externalId,
            'order_id' => $orderId,
            'lat' => $lat,
            'lon' => $lon,
            'zone_id' => $assign['zone_id'] ?? $zoneId,
            'zone_name' => $assign['zone_name'] ?? null,
            'trip_id' => $assign['trip_id'] ?? null,
            'vehicle_id' => $assign['vehicle_id'] ?? null,
            'vehicle_name' => $assign['vehicle_name'] ?? null,
            'vehicle_plate' => $assign['vehicle_plate'] ?? null,
            'capacity_kg' => $assign['capacity_kg'] ?? 0,
            'loaded_kg' => $assign['loaded_kg'] ?? 0,
            'free_kg' => $assign['free_kg'] ?? 0,
            'overload' => !empty($assign['overload']),
            'overload_kg' => $assign['overload_kg'] ?? 0,
            'load_percent' => $assign['load_percent'] ?? 0,
            'message' => $assign['message'] ?? '',
            'unassigned' => $doUnassign || !empty($assign['unassigned']),
            'deleted_links' => $assign['deleted_links'] ?? 0,
            'geo_provider' => $geoProvider,
            'geo_error' => $geoError,
        ];
    } catch (Throwable $e) {
        $errors[] = "Item $i: " . $e->getMessage();
    }
}

echo json_encode([
    'ok' => empty($errors),
    'saved' => $saved,
    'errors' => $errors,
    'overload' => $anyOverload,
    'details' => $details,
    'has_coords_column' => $hasCoords,
], JSON_UNESCAPED_UNICODE);
