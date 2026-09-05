<?php
/**
 * Операции с заявкой в рейсе (для drag & drop на рабочем столе).
 * POST (JSON):
 *   { action: 'add',    order_id, to_trip_id }              — добавить заявку в рейс
 *   { action: 'move',   order_id, from_trip_id, to_trip_id } — перенести между рейсами
 *   { action: 'remove', order_id, trip_id }                  — убрать из рейса (в «новые»)
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
$orderId = (int) ($data['order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'order_id required']);
    exit;
}

$pdo = Database::pdo();

$chk = $pdo->prepare('SELECT id FROM orders WHERE id = ?');
$chk->execute([$orderId]);
if (!$chk->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'order not found']);
    exit;
}

if ($action === 'add' || $action === 'move') {
    $toTrip = (int) ($data['to_trip_id'] ?? 0);
    if ($toTrip <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'to_trip_id required']);
        exit;
    }
    $tchk = $pdo->prepare('SELECT id FROM trips WHERE id = ?');
    $tchk->execute([$toTrip]);
    if (!$tchk->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'trip not found']);
        exit;
    }

    // Заявка может быть только в одном рейсе — сначала убираем из старого
    $pdo->prepare('DELETE FROM trip_items WHERE order_id = ?')->execute([$orderId]);

    $mx = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM trip_items WHERE trip_id = ?');
    $mx->execute([$toTrip]);
    $sortOrder = (int) $mx->fetchColumn();

    $pdo->prepare('INSERT INTO trip_items (trip_id, order_id, sort_order) VALUES (?, ?, ?)')
        ->execute([$toTrip, $orderId, $sortOrder]);
    $pdo->prepare("UPDATE orders SET status = 'assigned' WHERE id = ?")->execute([$orderId]);

    echo json_encode(['ok' => true, 'action' => $action]);
    exit;
}

if ($action === 'remove') {
    $tripId = (int) ($data['trip_id'] ?? 0);
    $pdo->prepare('DELETE FROM trip_items WHERE order_id = ? AND trip_id = ?')->execute([$orderId, $tripId]);
    $pdo->prepare("UPDATE orders SET status = 'new' WHERE id = ?")->execute([$orderId]);
    echo json_encode(['ok' => true, 'action' => 'remove']);
    exit;
}

if ($action === 'reorder') {
    // Ручная перестановка заявок внутри рейса (drag & drop)
    $tripId = (int) ($data['trip_id'] ?? 0);
    $orderIds = $data['order_ids'] ?? [];
    if ($tripId <= 0 || !is_array($orderIds)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'trip_id and order_ids required']);
        exit;
    }
    $tchk = $pdo->prepare('SELECT id FROM trips WHERE id = ?');
    $tchk->execute([$tripId]);
    if (!$tchk->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'trip not found']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE trip_items SET sort_order = ? WHERE trip_id = ? AND order_id = ?');
    $i = 1;
    foreach ($orderIds as $oid) {
        $oid = (int) $oid;
        if ($oid > 0) { $stmt->execute([$i++, $tripId, $oid]); }
    }
    echo json_encode(['ok' => true, 'action' => 'reorder']);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unknown action']);
exit;