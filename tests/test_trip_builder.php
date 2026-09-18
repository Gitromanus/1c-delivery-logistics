<?php
// Smoke-тест TripBuilder на SQLite.
// 1) Шаблон route_templates определяет порядок заявок внутри рейса.
// 2) Машина, привязанная к двум зонам: при малой загрузке — один
//    объединённый рейс, при большой — раздельные рейсы.
require __DIR__ . '/../public/src/TripBuilder.php';

function makeDb(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
    CREATE TABLE zones (id INTEGER PRIMARY KEY, name TEXT, sort_order INT, is_active INT DEFAULT 1);
    CREATE TABLE vehicles (id INTEGER PRIMARY KEY, name TEXT, capacity_kg REAL, is_active INT);
    CREATE TABLE vehicle_zones (vehicle_id INT, zone_id INT, is_primary INT);
    CREATE TABLE orders (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      external_id TEXT, number TEXT, doc_date TEXT, partner TEXT, address TEXT,
      weight_kg REAL, zone_id INT, status TEXT
    );
    CREATE TABLE trips (id INTEGER PRIMARY KEY AUTOINCREMENT, trip_date TEXT, vehicle_id INT, zone_id INT, status TEXT, note TEXT);
    CREATE TABLE trip_items (id INTEGER PRIMARY KEY AUTOINCREMENT, trip_id INT, order_id INT, sort_order INT);
    CREATE TABLE route_templates (zone_id INT, partner TEXT, position INT, PRIMARY KEY (zone_id, partner));
    ");
    return $pdo;
}

function addOrder(PDO $pdo, string $partner, float $w, int $zone, string $date = '2026-09-18'): void
{
    static $n = 0;
    $pdo->prepare("INSERT INTO orders (external_id, number, doc_date, partner, address, weight_kg, zone_id, status)
                   VALUES (?, ?, ?, ?, 'addr', ?, ?, 'new')")
        ->execute(['ext-' . ++$n, 'ОРД-' . $n, $date, $partner, $w, $zone]);
}

$fail = 0;
function check(string $name, bool $ok): void
{
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . "\n";
    if (!$ok) $fail++;
}

// --- Тест 1: шаблон порядка ---
$pdo = makeDb();
$pdo->exec("INSERT INTO zones (id, name, sort_order) VALUES (1, 'Зона 1', 1)");
$pdo->exec("INSERT INTO vehicles (id, name, capacity_kg, is_active) VALUES (10, 'Газель', 1000, 1)");
$pdo->exec("INSERT INTO vehicle_zones VALUES (10, 1, 1)");
foreach ([['ООО Беркит', 300], ['ИП Соколов', 300], ['ООО Вишня', 300], ['Новый Клиент', 100]] as $i => [$p, $w]) {
    addOrder($pdo, $p, $w, 1);
}
$pdo->exec("INSERT INTO route_templates VALUES (1, 'ООО Вишня', 1), (1, 'ИП Соколов', 2), (1, 'ООО Беркит', 3)");
$res = TripBuilder::rebuild($pdo, '2026-09-18');
$rows = $pdo->query(
    "SELECT o.partner FROM trip_items ti JOIN orders o ON o.id = ti.order_id ORDER BY ti.sort_order"
)->fetchAll(PDO::FETCH_COLUMN);
check('шаблон: порядок в рейсе', $res['ok'] && $rows === ['ООО Вишня', 'ИП Соколов', 'ООО Беркит', 'Новый Клиент']);

// --- Тест 2: машина на 2 зоны, малая загрузка -> один объединённый рейс ---
$pdo = makeDb();
$pdo->exec("INSERT INTO zones (id, name, sort_order) VALUES (1, 'Северный', 1), (2, 'Южный', 2)");
$pdo->exec("INSERT INTO vehicles (id, name, capacity_kg, is_active) VALUES (10, 'Газель', 1000, 1)");
$pdo->exec("INSERT INTO vehicle_zones VALUES (10, 1, 1), (10, 2, 0)");
addOrder($pdo, 'ООО Вишня', 200, 1);
addOrder($pdo, 'ООО Беркит', 150, 1);
addOrder($pdo, 'ИП Соколов', 300, 2);
$res = TripBuilder::rebuild($pdo, '2026-09-18');
$trips = $pdo->query(
    "SELECT t.id, t.zone_id, t.note, v.name AS veh,
            (SELECT COUNT(*) FROM trip_items ti WHERE ti.trip_id = t.id) AS n
     FROM trips t JOIN vehicles v ON v.id = t.vehicle_id ORDER BY t.id"
)->fetchAll(PDO::FETCH_ASSOC);
check('малая загрузка: один рейс', $res['ok'] && count($trips) === 1 && $trips[0]['n'] === 3);
check('малая загрузка: пометка объединённого рейса',
    count($trips) === 1 && strpos((string) $trips[0]['note'], 'Северный + Южный') !== false);
// Порядок: заявки зоны 1 (по шаблону/по id), потом зоны 2
$seq = $pdo->query(
    "SELECT o.zone_id FROM trip_items ti JOIN trips t ON t.id = ti.trip_id
     JOIN orders o ON o.id = ti.order_id WHERE t.id = " . (int) $trips[0]['id'] . " ORDER BY ti.sort_order"
)->fetchAll(PDO::FETCH_COLUMN);
check('малая загрузка: заявки зоны 1 идут перед зоной 2', $seq === ['1', '1', '2'] || $seq === [1, 1, 2]);

// --- Тест 3: большая загрузка -> раздельные рейсы ---
$pdo = makeDb();
$pdo->exec("INSERT INTO zones (id, name, sort_order) VALUES (1, 'Северный', 1), (2, 'Южный', 2)");
$pdo->exec("INSERT INTO vehicles (id, name, capacity_kg, is_active) VALUES (10, 'Газель', 1000, 1)");
$pdo->exec("INSERT INTO vehicle_zones VALUES (10, 1, 1), (10, 2, 0)");
addOrder($pdo, 'ООО Вишня', 700, 1);
addOrder($pdo, 'ИП Соколов', 800, 2);
$res = TripBuilder::rebuild($pdo, '2026-09-18');
$trips = $pdo->query("SELECT zone_id FROM trips ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
check('большая загрузка: раздельные рейсы по зонам',
    $res['ok'] && count($trips) === 2 && ($trips === ['1', '2'] || $trips === [1, 2]));

exit($fail ? 1 : 0);
