<?php
// Smoke-тест TripBuilder на SQLite: шаблон route_templates должен
// определять порядок заявок внутри рейса.
require __DIR__ . '/../public/src/TripBuilder.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("
CREATE TABLE zones (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE vehicles (id INTEGER PRIMARY KEY, name TEXT, capacity_kg REAL, is_active INT);
CREATE TABLE vehicle_zones (vehicle_id INT, zone_id INT, is_primary INT);
CREATE TABLE orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  external_id TEXT, number TEXT, doc_date TEXT, partner TEXT, address TEXT,
  weight_kg REAL, zone_id INT, status TEXT
);
CREATE TABLE trips (id INTEGER PRIMARY KEY AUTOINCREMENT, trip_date TEXT, vehicle_id INT, zone_id INT, status TEXT);
CREATE TABLE trip_items (id INTEGER PRIMARY KEY AUTOINCREMENT, trip_id INT, order_id INT, sort_order INT);
CREATE TABLE route_templates (zone_id INT, partner TEXT, position INT, PRIMARY KEY (zone_id, partner));
");

$pdo->exec("INSERT INTO zones (id, name) VALUES (1, 'Зона 1')");
$pdo->exec("INSERT INTO vehicles (id, name, capacity_kg, is_active) VALUES (10, 'Газель', 1000, 1)");
$pdo->exec("INSERT INTO vehicle_zones VALUES (10, 1, 1)");

// Клиенты: template порядок — ООО Вишня (1), ИП Соколов (2), ООО Беркит (3)
$orders = [
    ['ООО Беркит', 300],
    ['ИП Соколов', 300],
    ['ООО Вишня', 300],
    ['Новый Клиент', 100], // нет в шаблоне — должен быть в конце
];
foreach ($orders as $i => [$partner, $w]) {
    $pdo->prepare("INSERT INTO orders (external_id, number, doc_date, partner, address, weight_kg, zone_id, status)
                   VALUES (?, ?, '2026-09-18', ?, 'addr', ?, 1, 'new')")
        ->execute(['ext-' . $i, 'ОРД-' . $i, $partner, $w]);
}
$pdo->exec("INSERT INTO route_templates VALUES (1, 'ООО Вишня', 1), (1, 'ИП Соколов', 2), (1, 'ООО Беркит', 3)");

$res = TripBuilder::rebuild($pdo, '2026-09-18');
var_export($res);
echo "\n";

$rows = $pdo->query(
    "SELECT o.partner, ti.sort_order FROM trip_items ti
     JOIN trips t ON t.id = ti.trip_id
     JOIN orders o ON o.id = ti.order_id
     ORDER BY ti.sort_order"
)->fetchAll(PDO::FETCH_ASSOC);

echo "Порядок в рейсе:\n";
foreach ($rows as $r) {
    echo $r['sort_order'] . '. ' . $r['partner'] . "\n";
}

$expected = ['ООО Вишня', 'ИП Соколов', 'ООО Беркит', 'Новый Клиент'];
$actual = array_column($rows, 'partner');
echo ($actual === $expected) ? "\nPASS: порядок соответствует шаблону\n" : "\nFAIL: " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
