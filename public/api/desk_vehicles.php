<?php
/** Машины по зонам на дату (из рейсов). Для рабочего стола. */
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Временная диагностика: ?diag=1 — какой src/DeskVehicles.php реально на сервере
if (isset($_GET['diag'])) {
    $f = dirname(__DIR__) . '/src/DeskVehicles.php';
    header('Content-Type: text/plain; charset=utf-8');
    echo 'file_exists=' . var_export(is_file($f), true) . "\n";
    echo 'md5=' . (is_file($f) ? md5_file($f) : '-') . "\n";
    echo 'has_v2=' . (is_file($f) ? var_export(strpos((string) file_get_contents($f), 'v2 (2026-09-18)') !== false, true) : '-') . "\n";
    echo 'loaded=' . var_export(class_exists('DeskVehicles'), true) . "\n";
    echo 'refl=' . (class_exists('DeskVehicles') ? (new ReflectionClass('DeskVehicles'))->getFileName() : '-') . "\n";
    exit;
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
