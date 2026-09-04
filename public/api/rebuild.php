<?php

session_start();
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// MVP: сборка доступна с рабочего стола.
// При публикации на поддомене лучше закрыть /api/ через пароль Beget или IP.

$date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['ok' => false, 'error' => 'Bad date']);
    exit;
}

$result = TripBuilder::rebuild(Database::pdo(), $date);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
