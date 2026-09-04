<?php
/**
 * ВРЕМЕННЫЙ инструмент: пересчёт зоны для уже загруженных заявок по их координатам
 * (попадание точки в полигон зоны). Без авторизации — как save_coords.php.
 * Запрос: GET/POST ?date=YYYY-MM-DD (необязательно; без даты — все заявки с координатами).
 */
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = Database::pdo();

$date = $_GET['date'] ?? $_POST['date'] ?? null;

$sql = "SELECT id, lat, lon, zone_id FROM orders WHERE lat IS NOT NULL AND lon IS NOT NULL";
$params = [];
if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $sql .= " AND doc_date = ?";
    $params[] = $date;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$upd = $pdo->prepare('UPDATE orders SET zone_id = ? WHERE id = ?');
$updated = 0;
$cleared = 0;

foreach ($rows as $r) {
    $newZone = ZoneMatcher::matchByCoords($pdo, (float) $r['lat'], (float) $r['lon']);
    $oldZone = $r['zone_id'] !== null ? (int) $r['zone_id'] : null;

    if ($newZone !== null && $newZone !== $oldZone) {
        $upd->execute([$newZone, (int) $r['id']]);
        $updated++;
    } elseif ($newZone === null && $oldZone !== null) {
        $upd->execute([null, (int) $r['id']]);
        $cleared++;
    }
}

echo json_encode([
    'ok' => true,
    'processed' => count($rows),
    'updated' => $updated,
    'cleared' => $cleared,
], JSON_UNESCAPED_UNICODE);