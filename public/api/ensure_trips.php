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

if (!class_exists('EnsureTrips')) {
    require_once (is_dir(dirname(__DIR__) . '/src') ? dirname(__DIR__) : __DIR__ . '/..') . '/src/EnsureTrips.php';
}

// bootstrap already loads some classes; force EnsureTrips
$src = dirname(__DIR__) . '/src/EnsureTrips.php';
if (is_file($src)) {
    require_once $src;
}

$result = EnsureTrips::forDate(Database::pdo(), $date);
echo json_encode(['ok' => true, 'date' => $date] + $result, JSON_UNESCAPED_UNICODE);
