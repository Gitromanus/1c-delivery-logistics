<?php
/**
 * Загрузка зон на дату:
 * вес/кол-во заявок в рейсах зоны + нераспределённые с этой zone_id.
 */
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$pdo = Database::pdo();
$stats = [];

$tripLoadStmt = $pdo->prepare(
    "SELECT t.zone_id,
            COUNT(ti.order_id) AS cnt,
            COALESCE(SUM(o.weight_kg), 0) AS weight
     FROM trips t
     JOIN trip_items ti ON ti.trip_id = t.id
     JOIN orders o ON o.id = ti.order_id
     WHERE t.trip_date = ?
       AND t.status <> 'cancelled'
       AND t.zone_id IS NOT NULL
       AND o.status <> 'cancelled'
     GROUP BY t.zone_id"
);
$tripLoadStmt->execute([$date]);
foreach ($tripLoadStmt->fetchAll() as $row) {
    $zid = (int) $row['zone_id'];
    $stats[$zid] = [
        'zone_id' => $zid,
        'cnt' => (int) $row['cnt'],
        'weight' => (float) $row['weight'],
    ];
}

$freeLoadStmt = $pdo->prepare(
    "SELECT o.zone_id,
            COUNT(*) AS cnt,
            COALESCE(SUM(o.weight_kg), 0) AS weight
     FROM orders o
     LEFT JOIN trip_items ti ON ti.order_id = o.id
     WHERE o.doc_date = ?
       AND o.status = 'new'
       AND o.zone_id IS NOT NULL
       AND ti.id IS NULL
     GROUP BY o.zone_id"
);
$freeLoadStmt->execute([$date]);
foreach ($freeLoadStmt->fetchAll() as $row) {
    $zid = (int) $row['zone_id'];
    if (!isset($stats[$zid])) {
        $stats[$zid] = ['zone_id' => $zid, 'cnt' => 0, 'weight' => 0.0];
    }
    $stats[$zid]['cnt'] += (int) $row['cnt'];
    $stats[$zid]['weight'] += (float) $row['weight'];
}

echo json_encode([
    'ok' => true,
    'date' => $date,
    'stats' => array_values($stats),
], JSON_UNESCAPED_UNICODE);
