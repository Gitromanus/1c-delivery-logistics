<?php
/**
 * Лёгкий опрос: изменились ли заявки/рейсы на дату.
 * GET ?date=YYYY-MM-dd
 */
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$pdo = Database::pdo();

$o = $pdo->prepare(
    "SELECT COUNT(*) AS cnt,
            COALESCE(MAX(id), 0) AS max_id,
            COALESCE(MAX(UNIX_TIMESTAMP(updated_at)), 0) AS max_upd
     FROM orders
     WHERE doc_date = ? AND status <> 'cancelled'"
);
$o->execute([$date]);
$ord = $o->fetch() ?: ['cnt' => 0, 'max_id' => 0, 'max_upd' => 0];

$t = $pdo->prepare(
    "SELECT COUNT(*) AS cnt, COALESCE(MAX(id), 0) AS max_id
     FROM trips WHERE trip_date = ? AND status <> 'cancelled'"
);
$t->execute([$date]);
$tr = $t->fetch() ?: ['cnt' => 0, 'max_id' => 0];

$free = $pdo->prepare(
    "SELECT COUNT(*) FROM orders WHERE doc_date = ? AND status = 'new'"
);
$free->execute([$date]);
$freeCnt = (int) $free->fetchColumn();

$version = implode('-', [
    (int) $ord['cnt'],
    (int) $ord['max_id'],
    (int) $ord['max_upd'],
    (int) $tr['cnt'],
    (int) $tr['max_id'],
    $freeCnt,
]);

echo json_encode([
    'ok' => true,
    'date' => $date,
    'version' => $version,
    'orders' => (int) $ord['cnt'],
    'free' => $freeCnt,
], JSON_UNESCAPED_UNICODE);
