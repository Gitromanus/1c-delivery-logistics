<?php
/**
 * Перенос машины в зону на выбранную дату.
 * - Рейсы этой машины на DATE → новая зона (история других дней не трогаем).
 * - vehicle_zones обновляем только если DATE = сегодня (шаблон «по умолчанию»).
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
        // Глобальную привязку меняем только «на сегодня»
        if ($date === date('Y-m-d')) {
            $pdo->prepare('DELETE FROM vehicle_zones WHERE vehicle_id = ?')->execute([$vehicleId]);
            $pdo->prepare(
                'INSERT IGNORE INTO vehicle_zones (vehicle_id, zone_id, is_primary) VALUES (?, ?, 1)'
            )->execute([$vehicleId, $zoneId]);
        }

        // Есть рейс на дату — сдвинуть зону; нет — создать пустой draft
        $exist = $pdo->prepare(
            "SELECT id FROM trips WHERE vehicle_id = ? AND trip_date = ? AND status <> 'cancelled' LIMIT 1"
        );
        $exist->execute([$vehicleId, $date]);
        $tripId = $exist->fetchColumn();

        if ($tripId) {
            $pdo->prepare(
                "UPDATE trips SET zone_id = ? WHERE vehicle_id = ? AND trip_date = ? AND status <> 'cancelled'"
            )->execute([$zoneId, $vehicleId, $date]);
            $tripsUpdated = 1;
        } else {
            $pdo->prepare(
                "INSERT INTO trips (trip_date, vehicle_id, zone_id, status) VALUES (?, ?, ?, 'draft')"
            )->execute([$date, $vehicleId, $zoneId]);
            $tripsUpdated = 1;
        }

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
        'defaults_updated' => $date === date('Y-m-d'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unknown action']);
exit;
