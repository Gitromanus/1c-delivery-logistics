<?php
/**
 * POST/GET ?date=YYYY-MM-DD — создать пустые рейсы для машин без рейса на дату.
 */
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['ok' => false, 'error' => 'Bad date']);
    exit;
}

$src = dirname(__DIR__) . '/src/EnsureTrips.php';
if (!class_exists('EnsureTrips') && is_file($src)) {
    require_once $src;
}

if (!class_exists('EnsureTrips')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'EnsureTrips class missing']);
    exit;
}

$result = EnsureTrips::forDate(Database::pdo(), $date);
echo json_encode(['ok' => true, 'date' => $date] + $result, JSON_UNESCAPED_UNICODE);
