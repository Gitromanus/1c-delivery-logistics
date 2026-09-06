<?php
/**
 * Выгрузка рейсов на дату для печати комплектов в 1С.
 *
 * GET /api/trips_export.php?date=YYYY-MM-DD
 * Header: X-Api-Key: <api_key из config>
 *
 * Ответ: { ok, date, trips: [ { trip_id, vehicle, plate, zone, orders: [...] } ] }
 * orders.external_id = GUID реализации из 1С
 */
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
$apiKey = (string) ($config['api_key'] ?? $config['ApiKey1s'] ?? '');

$given = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
if ($apiKey === '' || !hash_equals($apiKey, (string) $given)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'GET or POST']);
    exit;
}

$date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Bad date, use YYYY-MM-DD'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = Database::pdo();

$tripsStmt = $pdo->prepare(
    "SELECT t.id AS trip_id,
            t.trip_date,
            t.status AS trip_status,
            t.note,
            v.id AS vehicle_id,
            v.name AS vehicle_name,
            v.plate,
            v.capacity_kg,
            z.id AS zone_id,
            z.name AS zone_name
     FROM trips t
     JOIN vehicles v ON v.id = t.vehicle_id
     LEFT JOIN zones z ON z.id = t.zone_id
     WHERE t.trip_date = ?
       AND t.status <> 'cancelled'
     ORDER BY t.id"
);
$tripsStmt->execute([$date]);
$tripRows = $tripsStmt->fetchAll();

$trips = [];
if ($tripRows) {
    $ids = array_map('intval', array_column($tripRows, 'trip_id'));
    $in = implode(',', $ids);

    $itemsStmt = $pdo->query(
        "SELECT ti.trip_id,
                ti.sort_order,
                o.id AS order_id,
                o.external_id,
                o.number,
                o.doc_date,
                o.partner,
                o.address,
                o.weight_kg,
                o.amount,
                o.status AS order_status,
                o.lat,
                o.lon
         FROM trip_items ti
         JOIN orders o ON o.id = ti.order_id
         WHERE ti.trip_id IN ($in)
         ORDER BY ti.trip_id, ti.sort_order, o.id"
    );
    $byTrip = [];
    foreach ($itemsStmt->fetchAll() as $row) {
        $tid = (int) $row['trip_id'];
        $byTrip[$tid][] = [
            'sort_order' => (int) $row['sort_order'],
            'external_id' => (string) $row['external_id'],
            'number' => $row['number'],
            'doc_date' => $row['doc_date'],
            'partner' => $row['partner'],
            'address' => $row['address'],
            'weight_kg' => (float) $row['weight_kg'],
            'amount' => $row['amount'] !== null ? (float) $row['amount'] : null,
            'status' => $row['order_status'],
            'lat' => $row['lat'] !== null ? (float) $row['lat'] : null,
            'lon' => $row['lon'] !== null ? (float) $row['lon'] : null,
        ];
    }

    foreach ($tripRows as $t) {
        $tid = (int) $t['trip_id'];
        $orders = $byTrip[$tid] ?? [];
        $weightSum = 0.0;
        foreach ($orders as $o) {
            $weightSum += (float) $o['weight_kg'];
        }
        $trips[] = [
            'trip_id' => $tid,
            'trip_date' => $t['trip_date'],
            'status' => $t['trip_status'],
            'note' => $t['note'],
            'vehicle_id' => (int) $t['vehicle_id'],
            'vehicle' => $t['vehicle_name'],
            'plate' => $t['plate'],
            'capacity_kg' => (float) $t['capacity_kg'],
            'zone_id' => $t['zone_id'] !== null ? (int) $t['zone_id'] : null,
            'zone' => $t['zone_name'],
            'orders_count' => count($orders),
            'weight_kg' => round($weightSum, 2),
            'orders' => $orders,
        ];
    }
}

echo json_encode([
    'ok' => true,
    'date' => $date,
    'trips_count' => count($trips),
    'trips' => $trips,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
