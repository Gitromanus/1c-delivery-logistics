<?php
/**
 * Сохранение / удаление полигона зоны.
 * POST { zone_id, polygon:[[lat,lon],...], color?, action:'save'|'delete' }
 * Авторизация: сессия админа или X-Api-Key.
 */
session_start();
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
$apiKey = (string) ($config['api_key'] ?? '');
$given = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (empty($_SESSION['admin_ok']) && !hash_equals($apiKey, (string) $given)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

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

$zoneId = (int) ($data['zone_id'] ?? 0);
$action = $data['action'] ?? 'save';

if ($zoneId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'zone_id required']);
    exit;
}

$pdo = Database::pdo();
$chk = $pdo->prepare('SELECT id FROM zones WHERE id = ?');
$chk->execute([$zoneId]);
if (!$chk->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'zone not found']);
    exit;
}

if ($action === 'delete') {
    $pdo->prepare('DELETE FROM zone_polygons WHERE zone_id = ?')->execute([$zoneId]);
    echo json_encode(['ok' => true, 'zone_id' => $zoneId, 'deleted' => true]);
    exit;
}

$polygon = $data['polygon'] ?? null;
if (!is_array($polygon)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'polygon array required']);
    exit;
}
$clean = [];
foreach ($polygon as $pt) {
    if (is_array($pt) && count($pt) >= 2 && is_numeric($pt[0]) && is_numeric($pt[1])) {
        $clean[] = [(float) $pt[0], (float) $pt[1]];
    }
}
if (count($clean) < 3) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'need >= 3 points']);
    exit;
}

$color = isset($data['color']) ? trim((string) $data['color']) : '';
$polyText = json_encode($clean, JSON_UNESCAPED_UNICODE);

$upd = $pdo->prepare(
    'INSERT INTO zone_polygons (zone_id, polygon, color) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE polygon = VALUES(polygon), color = VALUES(color)'
);
$upd->execute([$zoneId, $polyText, $color !== '' ? $color : null]);

echo json_encode([
    'ok' => true,
    'zone_id' => $zoneId,
    'points' => count($clean),
], JSON_UNESCAPED_UNICODE);