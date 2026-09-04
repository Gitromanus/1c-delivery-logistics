<?php
/**
 * Геокодирование заявок без координат для выбранной даты — серверная часть,
 * вызывается кнопкой «Геокод с карты» (без авторизации, как save_coords.php).
 * Обрабатывает батч до 20 заявок за раз, с паузой между запросами (лимиты Nominatim).
 */
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
$yandexKey = (string) ($config['yandex_geocoder_key'] ?? ($config['yandex_maps_key'] ?? ''));
$dadataToken = (string) ($config['dadata_token'] ?? '');

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
$errors = [];
$providers = [];

foreach ($rows as $row) {
    $meta = Geocoder::geocodeWithMeta($row['address'], $yandexKey, $dadataToken);
    if ($meta['point']) {
        $upd->execute([$meta['point']['lat'], $meta['point']['lon'], $row['id']]);
        $ok++;
        $p = $meta['provider'] ?? '?';
        $providers[$p] = ($providers[$p] ?? 0) + 1;
    } else {
        $fail++;
        if (count($errors) < 5) {
            $errors[] = [
                'id' => (int) $row['id'],
                'address' => $row['address'],
                'error' => $meta['error'],
            ];
        }
    }
    // Пауза только для бесплатных OSM-провайдеров (Nominatim/Photon) из-за их лимитов
    if (in_array($meta['provider'] ?? '', ['nominatim', 'photon'], true)) {
        usleep(1100000);
    }
}

echo json_encode([
    'ok' => true,
    'geocoded' => $ok,
    'failed' => $fail,
    'left_batch' => count($rows),
    'providers' => $providers,
    'sample_errors' => $errors,
], JSON_UNESCAPED_UNICODE);