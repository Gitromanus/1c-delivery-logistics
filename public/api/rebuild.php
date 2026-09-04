<?php

session_start();
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$config = require dirname(__DIR__, 2) . '/config.php';
if (empty($_SESSION['admin_ok'])) {
    // allow same api key for automation
    $given = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!hash_equals((string) ($config['api_key'] ?? ''), (string) $given)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

$date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['ok' => false, 'error' => 'Bad date']);
    exit;
}

$result = TripBuilder::rebuild(Database::pdo(), $date);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
