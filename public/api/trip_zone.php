<?php
/**
 * Добавить / убрать район у рейса на выбранную дату (кнопка «+» в шапке рейса).
 * POST (JSON):
 *   { action: 'add',    trip_id, zone_id } — заявки района перетягиваются в рейс
 *                                            (из других рейсов даты и нераспределённые),
 *                                            пустые draft-рейсы источники удаляются
 *   { action: 'remove', trip_id, zone_id } — заявки района выходят из рейса в «нераспределённые»
 * Пометка рейса (note) пересчитывается по фактическому составу заявок.
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
$tripId = (int) ($data['trip_id'] ?? 0);
$zoneId = (int) ($data['zone_id'] ?? 0);

if (!in_array($action, ['add', 'remove'], true) || $tripId <= 0 || $zoneId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'action (add|remove), trip_id, zone_id required']);
    exit;
}

$pdo = Database::pdo();

$tchk = $pdo->prepare(
    "SELECT id, trip_date, zone_id, status FROM trips WHERE id = ? AND status <> 'cancelled'"
);
$tchk->execute([$tripId]);
$trip = $tchk->fetch();
if (!$trip) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'trip not found']);
    exit;
}
if ($trip['status'] === 'done') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'рейс уже закрыт']);
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

$tripDate = $trip['trip_date'];

try {
    $pdo->beginTransaction();

    if ($action === 'add') {
        // Заявки района из других рейсов этой даты (кроме целевого)
        $src = $pdo->prepare(
            "SELECT ti.order_id, ti.trip_id
             FROM trip_items ti
             JOIN trips t2 ON t2.id = ti.trip_id
             JOIN orders o ON o.id = ti.order_id
             WHERE t2.trip_date = ? AND t2.id <> ? AND t2.status IN ('draft','confirmed')
               AND o.zone_id = ?"
        );
        $src->execute([$tripDate, $tripId, $zoneId]);
        $moved = $src->fetchAll(PDO::FETCH_ASSOC);
        $sourceTripIds = [];
        $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM trip_items WHERE trip_id = ?');
        $maxStmt->execute([$tripId]);
        $sort = (int) $maxStmt->fetchColumn();
        $insItem = $pdo->prepare('INSERT IGNORE INTO trip_items (trip_id, order_id, sort_order) VALUES (?, ?, ?)');
        $markAssigned = $pdo->prepare("UPDATE orders SET status = 'assigned' WHERE id = ?");
        foreach ($moved as $row) {
            $sort++;
            $insItem->execute([$tripId, (int) $row['order_id'], $sort]);
            $markAssigned->execute([(int) $row['order_id']]);
            if ((int) $row['trip_id'] !== $tripId) {
                $sourceTripIds[(int) $row['trip_id']] = true;
            }
        }

        // Нераспределённые заявки района на эту дату
        $free = $pdo->prepare(
            "SELECT id FROM orders
             WHERE doc_date = ? AND zone_id = ? AND status = 'new'
             ORDER BY id"
        );
        $free->execute([$tripDate, $zoneId]);
        foreach ($free->fetchAll(PDO::FETCH_COLUMN) as $oid) {
            $sort++;
            $insItem->execute([$tripId, (int) $oid, $sort]);
            $markAssigned->execute([(int) $oid]);
        }

        // Опустевшие draft-рейсы-источники убираем
        foreach (array_keys($sourceTripIds) as $sid) {
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM trip_items WHERE trip_id = ?');
            $cnt->execute([$sid]);
            if ((int) $cnt->fetchColumn() === 0) {
                $pdo->prepare("DELETE FROM trips WHERE id = ? AND status = 'draft'")->execute([$sid]);
            }
        }
    } else { // remove
        $get = $pdo->prepare(
            "SELECT ti.id, o.id AS order_id
             FROM trip_items ti
             JOIN orders o ON o.id = ti.order_id
             WHERE ti.trip_id = ? AND o.zone_id = ?"
        );
        $get->execute([$tripId, $zoneId]);
        $rows = $get->fetchAll(PDO::FETCH_ASSOC);
        $delItem = $pdo->prepare('DELETE FROM trip_items WHERE id = ?');
        $markNew = $pdo->prepare("UPDATE orders SET status = 'new' WHERE id = ?");
        foreach ($rows as $row) {
            $delItem->execute([(int) $row['id']]);
            $markNew->execute([(int) $row['order_id']]);
        }
    }

    // Пометка рейса: зоны целевого рейса + зоны фактически попавших в него заявок
    $zn = $pdo->prepare(
        "SELECT DISTINCT z.id, z.name, z.sort_order
         FROM trip_items ti
         JOIN orders o ON o.id = ti.order_id
         JOIN zones z ON z.id = o.zone_id
         WHERE ti.trip_id = ?"
    );
    $zn->execute([$tripId]);
    $merged = $zn->fetchAll(PDO::FETCH_ASSOC);
    $zoneSet = [];
    if ((int) $trip['zone_id'] > 0) {
        $zn2 = $pdo->prepare('SELECT id, name, sort_order FROM zones WHERE id = ?');
        $zn2->execute([(int) $trip['zone_id']]);
        $z = $zn2->fetch();
        if ($z) {
            $zoneSet[(int) $z['id']] = $z;
        }
    }
    foreach ($merged as $z) {
        if ((int) $z['id'] > 0) {
            $zoneSet[(int) $z['id']] = $z;
        }
    }
    usort($zoneSet, function ($a, $b) {
        return ((int) $a['sort_order'] <=> (int) $b['sort_order']) ?: ((int) $a['id'] <=> (int) $b['id']);
    });
    $note = null;
    if (count($zoneSet) > 1) {
        $note = 'Объединённый рейс: ' . implode(' + ', array_map(function ($z) {
            return $z['name'];
        }, $zoneSet));
    }
    $pdo->prepare('UPDATE trips SET note = ? WHERE id = ?')->execute([$note, $tripId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'ok' => true,
    'action' => $action,
    'trip_id' => $tripId,
    'zone_id' => $zoneId,
    'zone_name' => $zone['name'],
    'note' => $note,
], JSON_UNESCAPED_UNICODE);
