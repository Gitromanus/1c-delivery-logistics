<?php
/**
 * Операции с машиной и зоной (для drag & drop машин на рабочем столе).
 * POST (JSON):
 *   { action: 'move', vehicle_id, zone_id, date? } — перенести машину в зону
 *   и обновить zone_id у рейсов этой машины на дату.
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
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$action = (string) ($data['action'] ?? '');
$vehicleId = (int) ($data['vehicle_id'] ?? 0);
$zoneId = (int) ($data['zone_id'] ?? 0);
$date = (string) ($data['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

if ($vehicleId <= 0 || $zoneId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'vehicle_id and zone_id required']);
    exit;
}

$pdo = Database::pdo();

$chk = $pdo->prepare('SELECT id FROM vehicles WHERE id = ?');
$chk->execute([$vehicleId]);
if (!$chk->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'vehicle not found']);
    exit;
}
$zchk = $pdo->prepare('SELECT id, name FROM zones WHERE id = ?');
$zchk->execute([$zoneId]);
$zone = $zchk->fetch();
if (!$zone) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'zone not found']);
    exit;
}

if ($action === 'move') {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM vehicle_zones WHERE vehicle_id = ?')->execute([$vehicleId]);
        $pdo->prepare(
            'INSERT IGNORE INTO vehicle_zones (vehicle_id, zone_id, is_primary) VALUES (?, ?, 1)'
        )->execute([$vehicleId, $zoneId]);

        // Рейсы этой машины на дату — в новую зону
        $updTrips = $pdo->prepare(
            "UPDATE trips SET zone_id = ?
             WHERE vehicle_id = ? AND trip_date = ? AND status <> 'cancelled'"
        );
        $updTrips->execute([$zoneId, $vehicleId, $date]);
        $tripsUpdated = $updTrips->rowCount();

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'action' => 'move',
        'vehicle_id' => $vehicleId,
        'zone_id' => $zoneId,
        'zone_name' => $zone['name'],
        'date' => $date,
        'trips_updated' => $tripsUpdated,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unknown action']);
exit;
