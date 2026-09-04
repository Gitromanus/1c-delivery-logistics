<?php
/**
 * Геокодирование заявок без координат для выбранной даты — серверная часть,
 * вызывается кнопкой «Геокод с карты» (без авторизации, как save_coords.php).
 * Обрабатывает батч до 20 заявок за раз.
 */
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
$yandexKey = (string) ($config['yandex_geocoder_key'] ?? ($config['yandex_maps_key'] ?? ''));

$date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['ok' => false, 'error' => 'Bad date']);
    exit;
}

$pdo = Database::pdo();
$stmt = $pdo->prepare(
    "SELECT id, address FROM orders
     WHERE doc_date = ? AND (lat IS NULL OR lon IS NULL) AND address <> ''
     ORDER BY id DESC LIMIT 20"
);
$stmt->execute([$date]);
$rows = $stmt->fetchAll();

$upd = $pdo->prepare('UPDATE orders SET lat = ?, lon = ? WHERE id = ?');
$ok = 0;
$fail = 0;

foreach ($rows as $row) {
    $meta = Geocoder::geocodeWithMeta($row['address'], $yandexKey);
    if ($meta['point']) {
        $upd->execute([$meta['point']['lat'], $meta['point']['lon'], $row['id']]);
        $ok++;
    } else {
        $fail++;
    }
}

echo json_encode([
    'ok' => true,
    'geocoded' => $ok,
    'failed' => $fail,
    'left_batch' => count($rows),
], JSON_UNESCAPED_UNICODE);