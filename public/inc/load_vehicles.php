<?php
/** Подключить после $pdo и $date. Выставляет $vehByZone. */
if (!isset($pdo, $date)) {
    return;
}
if (class_exists('DeskVehicles')) {
    $vehByZone = DeskVehicles::byZoneForDate($pdo, $date);
} else {
    $vehByZone = [];
    $vehZoneRows = $pdo->query(
        "SELECT vz.zone_id, v.id AS vehicle_id, v.name, v.capacity_kg
         FROM vehicle_zones vz
         JOIN vehicles v ON v.id = vz.vehicle_id
         WHERE v.is_active = 1
         ORDER BY v.name"
    )->fetchAll();
    foreach ($vehZoneRows as $vr) {
        $vehByZone[(int) $vr['zone_id']][] = $vr;
    }
}
