<?php
require dirname(__DIR__) . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireAdmin('../login.php');
} else {
    $config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
    if (isset($_POST['password'])) {
        if (hash_equals((string)($config['admin_password'] ?? ''), (string)$_POST['password'])) {
            $_SESSION['admin_ok'] = true;
            header('Location: index.php');
            exit;
        }
        $error = 'Неверный пароль';
    }
    if (empty($_SESSION['admin_ok'])): ?>
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><title>Админка — вход</title>
<link rel="stylesheet" href="../assets/css/style.css"></head>
<body><div class="app" style="max-width:400px">
<h1 class="logo" style="margin-bottom:16px">Админка логистики</h1>
<?php if (!empty($error)): ?><div class="flash flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="panel">
<label class="muted">Пароль</label><br>
<input type="password" name="password" style="width:100%;margin:8px 0 12px" required>
<button class="btn btn-primary" type="submit">Войти</button>
</form>
<p class="muted" style="margin-top:12px"><a href="../">← На рабочий стол</a></p>
</div></body></html>
<?php exit; endif;
}

$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
$pdo = Database::pdo();
$msg = '';
$err = '';
$tab = $_GET['tab'] ?? 'main';
if (!in_array($tab, ['main', 'users', 'settings'], true)) $tab = 'main';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  try {
    switch ($_POST['action']) {
        case 'add_zone':
            $pdo->prepare('INSERT INTO zones (name, code, sort_order) VALUES (?,?,?)')
                ->execute([trim($_POST['name'] ?? ''), trim($_POST['code'] ?? '') ?: null, (int) ($_POST['sort_order'] ?? 0)]);
            $msg = 'Зона добавлена';
            break;
        case 'edit_zone':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare('UPDATE zones SET name=?, code=?, sort_order=? WHERE id=?')
                    ->execute([trim($_POST['name'] ?? ''), trim($_POST['code'] ?? '') ?: null, (int) ($_POST['sort_order'] ?? 0), $id]);
                $msg = 'Зона изменена';
            }
            break;
        case 'delete_zone':
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM zone_polygons WHERE zone_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM vehicle_zones WHERE zone_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM zones WHERE id=?')->execute([$id]);
            $msg = 'Зона удалена';
            break;
        case 'add_vehicle':
            $pdo->prepare('INSERT INTO vehicles (name, plate, capacity_kg) VALUES (?,?,?)')
                ->execute([trim($_POST['name'] ?? ''), trim($_POST['plate'] ?? '') ?: null, (float) ($_POST['capacity_kg'] ?? 900)]);
            $msg = 'Машина добавлена';
            break;
        case 'edit_vehicle':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare('UPDATE vehicles SET name=?, plate=?, capacity_kg=? WHERE id=?')
                    ->execute([trim($_POST['name'] ?? ''), trim($_POST['plate'] ?? '') ?: null, (float) ($_POST['capacity_kg'] ?? 900), $id]);
                $msg = 'Машина изменена';
            }
            break;
        case 'delete_vehicle':
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM vehicle_zones WHERE vehicle_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM vehicles WHERE id=?')->execute([$id]);
            $msg = 'Машина удалена';
            break;
        case 'bind':
            $pdo->prepare('INSERT INTO vehicle_zones (vehicle_id, zone_id, is_primary) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_primary=VALUES(is_primary)')
                ->execute([(int) ($_POST['vehicle_id'] ?? 0), (int) ($_POST['zone_id'] ?? 0), !empty($_POST['is_primary']) ? 1 : 0]);
            $msg = 'Привязка сохранена';
            break;
        case 'unbind':
            $pdo->prepare('DELETE FROM vehicle_zones WHERE vehicle_id=? AND zone_id=?')
                ->execute([(int) ($_POST['vehicle_id'] ?? 0), (int) ($_POST['zone_id'] ?? 0)]);
            $msg = 'Привязка удалена';
            break;
        case 'add_user':
            $login = trim((string) ($_POST['login'] ?? ''));
            $pass = (string) ($_POST['password'] ?? '');
            $role = (string) ($_POST['role'] ?? 'dispatcher');
            if (!in_array($role, ['admin', 'dispatcher', 'driver', 'sales'], true)) $role = 'dispatcher';
            if ($login === '' || $pass === '') throw new RuntimeException('Логин и пароль обязательны');
            $pdo->prepare('INSERT INTO users (login, password_hash, name, role, vehicle_id, zone_id, is_active) VALUES (?,?,?,?,?,?,1)')
                ->execute([
                    $login, password_hash($pass, PASSWORD_DEFAULT),
                    trim((string) ($_POST['name'] ?? '')) ?: null, $role,
                    $role === 'driver' && !empty($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : null,
                    $role === 'sales' && !empty($_POST['zone_id']) ? (int) $_POST['zone_id'] : null,
                ]);
            $msg = 'Пользователь добавлен';
            $tab = 'users';
            break;
        case 'edit_user':
            $id = (int) ($_POST['id'] ?? 0);
            $role = (string) ($_POST['role'] ?? 'dispatcher');
            if (!in_array($role, ['admin', 'dispatcher', 'driver', 'sales'], true)) $role = 'dispatcher';
            $pdo->prepare('UPDATE users SET name=?, role=?, vehicle_id=?, zone_id=?, is_active=? WHERE id=?')->execute([
                trim((string) ($_POST['name'] ?? '')) ?: null, $role,
                $role === 'driver' && !empty($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : null,
                $role === 'sales' && !empty($_POST['zone_id']) ? (int) $_POST['zone_id'] : null,
                !empty($_POST['is_active']) ? 1 : 0, $id,
            ]);
            if (!empty($_POST['password'])) {
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                    ->execute([password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $id]);
            }
            $msg = 'Пользователь сохранён';
            $tab = 'users';
            break;
        case 'delete_user':
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM users WHERE id=? AND login <> ?')->execute([$id, 'admin']);
            $msg = 'Пользователь удалён';
            $tab = 'users';
            break;
        case 'save_settings':
            if (class_exists('Settings')) {
                foreach (['api_key', 'yandex_maps_key', 'yandex_geocoder_key', 'dadata_token'] as $k) {
                    if (array_key_exists($k, $_POST)) Settings::set($k, trim((string) $_POST[$k]));
                }
                $msg = 'Настройки API сохранены';
            } else {
                throw new RuntimeException('Класс Settings не найден');
            }
            $tab = 'settings';
            break;
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

$zones = $pdo->query(
    "SELECT z.*, zp.color AS poly_color
     FROM zones z
     LEFT JOIN zone_polygons zp ON zp.zone_id = z.id
     ORDER BY z.sort_order, z.name"
)->fetchAll();
$vehicles = $pdo->query('SELECT * FROM vehicles ORDER BY name')->fetchAll();
$binds = $pdo->query(
    'SELECT vz.*, v.name AS vname, z.name AS zname FROM vehicle_zones vz
     JOIN vehicles v ON v.id = vz.vehicle_id
     JOIN zones z ON z.id = vz.zone_id'
)->fetchAll();
$zonePolys = $pdo->query('SELECT zone_id, polygon, color FROM zone_polygons')->fetchAll();
$polyMap = [];
foreach ($zonePolys as $zp) {
    $polyMap[(int) $zp['zone_id']] = [
        'points' => json_decode((string) $zp['polygon'], true) ?: [],
        'color' => (string) ($zp['color'] ?? ''),
    ];
}

$users = [];
try {
    $users = $pdo->query(
        'SELECT u.*, v.name AS vname, z.name AS zname
         FROM users u
         LEFT JOIN vehicles v ON v.id = u.vehicle_id
         LEFT JOIN zones z ON z.id = u.zone_id
         ORDER BY u.role, u.login'
    )->fetchAll();
} catch (Throwable $e) {}

$roleLabels = [
    'admin' => 'Админ',
    'dispatcher' => 'Диспетчер',
    'driver' => 'Водитель',
    'sales' => 'Торговый',
];

$apiKey = class_exists('Settings') ? Settings::get('api_key', '') : '';
$ymKey = class_exists('Settings') ? Settings::get('yandex_maps_key', '') : '';
$ygKey = class_exists('Settings') ? Settings::get('yandex_geocoder_key', '') : '';
$ddKey = class_exists('Settings') ? Settings::get('dadata_token', '') : '';
if ($apiKey === '') $apiKey = (string)($config['api_key'] ?? $config['ApiKey1s'] ?? '');
if ($ymKey === '') $ymKey = (string)($config['yandex_maps_key'] ?? '');
if ($ygKey === '') $ygKey = (string)($config['yandex_geocoder_key'] ?? '');
if ($ddKey === '') $ddKey = (string)($config['dadata_token'] ?? '');
if (empty($config['yandex_maps_key']) && $ymKey !== '') {
    $config['yandex_maps_key'] = $ymKey;
}
?>
