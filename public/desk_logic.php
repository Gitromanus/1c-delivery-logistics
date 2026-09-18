<?php
$config = require (defined('APP_ROOT') ? APP_ROOT : __DIR__) . '/config.php';

$yandexKey = class_exists('Settings') ? (string) Settings::get('yandex_maps_key', $config['yandex_maps_key'] ?? '') : (string) ($config['yandex_maps_key'] ?? '');

$pdo = Database::pdo();
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$zones = $pdo->query(
    "SELECT z.*, zp.color AS poly_color
     FROM zones z
     LEFT JOIN zone_polygons zp ON zp.zone_id = z.id
     WHERE z.is_active = 1
     ORDER BY z.sort_order, z.name"
)->fetchAll();

$statsStmt = $pdo->prepare(
    "SELECT zone_id,
            COUNT(*) AS cnt,
            COALESCE(SUM(weight_kg),0) AS weight
     FROM orders
     WHERE doc_date = ? AND status <> 'cancelled'
     GROUP BY zone_id"
);
$statsStmt->execute([$date]);
$stats = [];
foreach ($statsStmt->fetchAll() as $row) {
    $stats[$row['zone_id'] ?? 0] = $row;
}

$tripsStmt = $pdo->prepare(
    "SELECT t.*, v.name AS vehicle_name, v.capacity_kg, v.plate, z.name AS zone_name
     FROM trips t
     JOIN vehicles v ON v.id = t.vehicle_id
     LEFT JOIN zones z ON z.id = t.zone_id
     WHERE t.trip_date = ? AND t.status <> 'cancelled'
     ORDER BY t.id"
);
$tripsStmt->execute([$date]);
$trips = $tripsStmt->fetchAll();

$deskFilter = class_exists('Auth') ? Auth::deskFilter() : ['mode' => 'all'];
if (($deskFilter['mode'] ?? '') === 'driver') {
    $vid = (int) ($deskFilter['vehicle_id'] ?? 0);
    $trips = array_values(array_filter($trips, function ($t) use ($vid) {
        return (int) $t['vehicle_id'] === $vid;
    }));
    $zoneIds = [];
    foreach ($trips as $t) {
        if (!empty($t['zone_id'])) {
            $zoneIds[(int) $t['zone_id']] = true;
        }
    }
    try {
        $st = $pdo->prepare('SELECT zone_id FROM vehicle_zones WHERE vehicle_id = ?');
        $st->execute([$vid]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $zid) {
            $zoneIds[(int) $zid] = true;
        }
    } catch (Throwable $e) {
    }
    $zones = array_values(array_filter($zones, function ($z) use ($zoneIds) {
        return isset($zoneIds[(int) $z['id']]);
    }));
} elseif (($deskFilter['mode'] ?? '') === 'sales') {
    $zid = (int) ($deskFilter['zone_id'] ?? 0);
    $zones = array_values(array_filter($zones, function ($z) use ($zid) {
        return (int) $z['id'] === $zid;
    }));
    $trips = array_values(array_filter($trips, function ($t) use ($zid) {
        return (int) ($t['zone_id'] ?? 0) === $zid;
    }));
}

$itemsByTrip = [];
$allowedOrderIds = []; // для фильтра карты
if ($trips) {
    $ids = array_column($trips, 'id');
    $in = implode(',', array_map('intval', $ids));
    $items = $pdo->query(
        "SELECT ti.trip_id, o.*, rt.position AS tpl_pos
         FROM trip_items ti
         JOIN orders o ON o.id = ti.order_id
         JOIN trips t ON t.id = ti.trip_id
         LEFT JOIN route_templates rt ON rt.zone_id = o.zone_id AND rt.partner = o.partner
         WHERE ti.trip_id IN ($in)
         ORDER BY ti.sort_order, o.id"
    )->fetchAll();
    foreach ($items as $it) {
        $itemsByTrip[$it['trip_id']][] = $it;
        $allowedOrderIds[(int) $it['id']] = true;
    }
}

$vehZoneRows = $pdo->query(
    "SELECT vz.zone_id, v.id AS vehicle_id, v.name, v.capacity_kg
     FROM vehicle_zones vz
     JOIN vehicles v ON v.id = vz.vehicle_id
     WHERE v.is_active = 1
     ORDER BY v.name"
)->fetchAll();
$vehByZone = [];
foreach ($vehZoneRows as $vr) {
    $vehByZone[(int) $vr['zone_id']][] = $vr;
}

// Машины по зонам: рейсы дня (включая объединённые, с пометками
// merged/empty) перекрывают статичные привязки из админки.
$tripVehByZone = class_exists('DeskVehicles')
    ? DeskVehicles::byZoneForDate($pdo, $date)
    : [];
foreach ($tripVehByZone as $zid => $vehList) {
    $vehByZone[$zid] = array_values($vehList);
}
$coveredZoneIds = array_map('intval', array_keys($tripVehByZone));

if (($deskFilter['mode'] ?? '') === 'driver') {
    $vid = (int) ($deskFilter['vehicle_id'] ?? 0);
    foreach ($vehByZone as $zid => $list) {
        $vehByZone[$zid] = array_values(array_filter($list, function ($v) use ($vid) {
            return (int) $v['vehicle_id'] === $vid;
        }));
    }
}

$unassigned = $pdo->prepare(
    "SELECT * FROM orders WHERE doc_date = ? AND status = 'new' ORDER BY id DESC LIMIT 100"
);
$unassigned->execute([$date]);
$freeOrders = $unassigned->fetchAll();

if (($deskFilter['mode'] ?? '') === 'driver') {
    $freeOrders = [];
} elseif (($deskFilter['mode'] ?? '') === 'sales') {
    $zid = (int) ($deskFilter['zone_id'] ?? 0);
    $freeOrders = array_values(array_filter($freeOrders, function ($o) use ($zid) {
        return (int) ($o['zone_id'] ?? 0) === $zid;
    }));
}

$mapOrders = $pdo->prepare(
    "SELECT id, number, external_id, partner, address, weight_kg, lat, lon, status, zone_id
     FROM orders WHERE doc_date = ? AND status <> 'cancelled'"
);
$mapOrders->execute([$date]);
$mapPoints = $mapOrders->fetchAll();

// Карта: только доступные по роли точки
if (($deskFilter['mode'] ?? '') === 'driver') {
    // только заявки в рейсе(ах) этой машины
    $mapPoints = array_values(array_filter($mapPoints, function ($p) use ($allowedOrderIds) {
        return isset($allowedOrderIds[(int) $p['id']]);
    }));
} elseif (($deskFilter['mode'] ?? '') === 'sales') {
    $zid = (int) ($deskFilter['zone_id'] ?? 0);
    // зона торгового + нераспределённые этой зоны (уже в freeOrders)
    $mapPoints = array_values(array_filter($mapPoints, function ($p) use ($zid, $allowedOrderIds) {
        $oz = (int) ($p['zone_id'] ?? 0);
        if ($oz === $zid) {
            return true;
        }
        // также точки, попавшие в рейсы этой зоны
        return isset($allowedOrderIds[(int) $p['id']]);
    }));
}

$zonePolys = $pdo->query(
    "SELECT zp.zone_id, zp.polygon, zp.color, z.name AS zone_name
     FROM zone_polygons zp JOIN zones z ON z.id = zp.zone_id"
)->fetchAll();

// Полигоны только доступных зон
if (($deskFilter['mode'] ?? '') === 'driver' || ($deskFilter['mode'] ?? '') === 'sales') {
    $allowedZoneIds = [];
    foreach ($zones as $z) {
        $allowedZoneIds[(int) $z['id']] = true;
    }
    $zonePolys = array_values(array_filter($zonePolys, function ($p) use ($allowedZoneIds) {
        return isset($allowedZoneIds[(int) $p['zone_id']]);
    }));
}

$needGeo = array_values(array_filter($mapPoints, function ($p) {
    return empty($p['lat']) || empty($p['lon']);
}));

if (!function_exists('h')) {
    function h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$tripStatusLabels = [
    'draft' => 'Черновик',
    'confirmed' => 'Подтверждён',
    'done' => 'Выполнен',
    'cancelled' => 'Отменён',
];
