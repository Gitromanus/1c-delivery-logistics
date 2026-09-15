<?php
require dirname(__DIR__) . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireAdmin('../login.php');
}

$pdo = Database::pdo();
$msg = '';
$err = '';

$vehicles = [];
$zones = [];
try {
    $vehicles = $pdo->query('SELECT id, name, plate FROM vehicles WHERE is_active = 1 ORDER BY name')->fetchAll();
    $zones = $pdo->query('SELECT id, name FROM zones WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
} catch (Throwable $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $login = trim((string) ($_POST['login'] ?? ''));
            $pass = (string) ($_POST['password'] ?? '');
            $role = (string) ($_POST['role'] ?? 'dispatcher');
            if (!in_array($role, ['admin', 'dispatcher', 'driver', 'sales'], true)) {
                $role = 'dispatcher';
            }
            if ($login === '' || $pass === '') {
                throw new RuntimeException('Логин и пароль обязательны');
            }
            $pdo->prepare(
                'INSERT INTO users (login, password_hash, name, role, vehicle_id, zone_id, is_active) VALUES (?,?,?,?,?,?,1)'
            )->execute([
                $login,
                password_hash($pass, PASSWORD_DEFAULT),
                trim((string) ($_POST['name'] ?? '')) ?: null,
                $role,
                $role === 'driver' && !empty($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : null,
                $role === 'sales' && !empty($_POST['zone_id']) ? (int) $_POST['zone_id'] : null,
            ]);
            $msg = 'Пользователь добавлен';
        } elseif ($action === 'edit') {
            $id = (int) ($_POST['id'] ?? 0);
            $role = (string) ($_POST['role'] ?? 'dispatcher');
            if (!in_array($role, ['admin', 'dispatcher', 'driver', 'sales'], true)) {
                $role = 'dispatcher';
            }
            $pdo->prepare(
                'UPDATE users SET name=?, role=?, vehicle_id=?, zone_id=?, is_active=? WHERE id=?'
            )->execute([
                trim((string) ($_POST['name'] ?? '')) ?: null,
                $role,
                $role === 'driver' && !empty($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : null,
                $role === 'sales' && !empty($_POST['zone_id']) ? (int) $_POST['zone_id'] : null,
                !empty($_POST['is_active']) ? 1 : 0,
                $id,
            ]);
            if (!empty($_POST['password'])) {
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                    ->execute([password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $id]);
            }
            $msg = 'Сохранено';
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM users WHERE id=? AND login <> ?')->execute([$id, 'admin']);
            $msg = 'Удалён';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
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
} catch (Throwable $e) {
    $err = 'Таблица users не найдена. Откройте /seed_admin.php один раз.';
}

$roleLabels = [
    'admin' => 'Админ',
    'dispatcher' => 'Диспетчер',
    'driver' => 'Водитель',
    'sales' => 'Торговый',
];
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Пользователи</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="../assets/js/theme.js"></script>
</head>
<body>
<div class="app">
  <div class="admin-nav">
    <a href="index.php">← Админка</a>
    <a href="users.php">Пользователи</a>
    <a href="settings.php">Настройки API</a>
    <a href="../index.php">Рабочий стол</a>
    <a href="../logout.php">Выйти</a>
  </div>
  <h1>Пользователи</h1>
  <?php if ($msg): ?><div class="flash flash-ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash flash-err"><?= h($err) ?></div><?php endif; ?>

  <section class="panel" style="margin-bottom:16px">
    <h2>Добавить</h2>
    <form method="post" style="display:grid;gap:8px;max-width:480px">
      <input type="hidden" name="action" value="add">
      <label>Логин <input name="login" required></label>
      <label>Пароль <input type="password" name="password" required></label>
      <label>Имя <input name="name"></label>
      <label>Роль
        <select name="role" id="roleAdd">
          <option value="dispatcher">Диспетчер</option>
          <option value="driver">Водитель</option>
          <option value="sales">Торговый представитель</option>
          <option value="admin">Админ</option>
        </select>
      </label>
      <label class="for-driver">Машина
        <select name="vehicle_id">
          <option value="">—</option>
          <?php foreach ($vehicles as $v): ?>
            <option value="<?= (int)$v['id'] ?>"><?= h($v['name'] . ($v['plate'] ? ' · '.$v['plate'] : '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="for-sales">Зона
        <select name="zone_id">
          <option value="">—</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= (int)$z['id'] ?>"><?= h($z['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-primary" type="submit">Создать</button>
    </form>
  </section>

  <section class="panel">
    <h2>Список</h2>
    <div style="overflow:auto">
    <table>
      <thead>
        <tr><th>Логин</th><th>Имя</th><th>Роль</th><th>Машина/Зона</th><th>Активен</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= h($u['login']) ?></td>
          <td><?= h($u['name'] ?? '') ?></td>
          <td><?= h($roleLabels[$u['role']] ?? $u['role']) ?></td>
          <td><?= h($u['vname'] ?? $u['zname'] ?? '—') ?></td>
          <td><?= (int)$u['is_active'] ? 'да' : 'нет' ?></td>
          <td>
            <details>
              <summary>Изменить</summary>
              <form method="post" style="margin-top:8px;display:grid;gap:6px;min-width:220px">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <input name="name" value="<?= h($u['name'] ?? '') ?>" placeholder="Имя">
                <select name="role">
                  <?php foreach ($roleLabels as $k=>$lab): ?>
                    <option value="<?= $k ?>" <?= $u['role']===$k?'selected':'' ?>><?= h($lab) ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="vehicle_id">
                  <option value="">Машина —</option>
                  <?php foreach ($vehicles as $v): ?>
                    <option value="<?= (int)$v['id'] ?>" <?= (int)($u['vehicle_id']??0)===(int)$v['id']?'selected':'' ?>><?= h($v['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="zone_id">
                  <option value="">Зона —</option>
                  <?php foreach ($zones as $z): ?>
                    <option value="<?= (int)$z['id'] ?>" <?= (int)($u['zone_id']??0)===(int)$z['id']?'selected':'' ?>><?= h($z['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="password" name="password" placeholder="Новый пароль (необязательно)">
                <label><input type="checkbox" name="is_active" value="1" <?= (int)$u['is_active']?'checked':'' ?>> Активен</label>
                <button class="btn btn-primary btn-sm" type="submit">Сохранить</button>
              </form>
              <?php if ($u['login'] !== 'admin'): ?>
              <form method="post" onsubmit="return confirm('Удалить?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit">Удалить</button>
              </form>
              <?php endif; ?>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>
</div>
</body>
</html>
