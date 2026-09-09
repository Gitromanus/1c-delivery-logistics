<?php
/** Машины по зонам на дату (из рейсов). Для рабочего стола. */
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$pdo = Database::pdo();
$vehByZone = class_exists('DeskVehicles')
    ? DeskVehicles::byZoneForDate($pdo, $date)
    : [];

// Плоский список + по зонам
$flat = [];
foreach ($vehByZone as $zid => $list) {
    foreach ($list as $v) {
        $flat[] = [
            'zone_id' => (int) $zid,
            'vehicle_id' => (int) $v['vehicle_id'],
            'name' => $v['name'],
            'capacity_kg' => (float) $v['capacity_kg'],
        ];
    }
}

echo json_encode([
    'ok' => true,
    'date' => $date,
    'by_zone' => $vehByZone,
    'vehicles' => $flat,
], JSON_UNESCAPED_UNICODE);
