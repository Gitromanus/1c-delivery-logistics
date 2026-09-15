<?php
/** Самодостаточная админка без загрузки с GitHub */
require dirname(__DIR__) . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireAdmin('../login.php');
}

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$pdo = Database::pdo();
$msg = '';
$err = '';

// --- POST actions (зоны / машины / привязки — упрощённо) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'add_zone':
                $pdo->prepare('INSERT INTO zones (name, code, sort_order) VALUES (?,?,?)')
                    ->execute([trim($_POST['name'] ?? ''), trim($_POST['code'] ?? '') ?: null, (int)($_POST['sort_order'] ?? 0)]);
                $msg = 'Зона добавлена';
                break;
            case 'add_vehicle':
                $pdo->prepare('INSERT INTO vehicles (name, plate, capacity_kg) VALUES (?,?,?)')
                    ->execute([trim($_POST['name'] ?? ''), trim($_POST['plate'] ?? '') ?: null, (float)($_POST['capacity_kg'] ?? 1000)]);
                $msg = 'Машина добавлена';
                break;
            case 'bind':
                $pdo->prepare('INSERT IGNORE INTO vehicle_zones (vehicle_id, zone_id, is_primary) VALUES (?,?,1)')
                    ->execute([(int)$_POST['vehicle_id'], (int)$_POST['zone_id']]);
                $msg = 'Привязка сохранена';
                break;
            case 'delete_zone':
                $id = (int)($_POST['id'] ?? 0);
                if ($id) {
                    $pdo->prepare('DELETE FROM vehicle_zones WHERE zone_id=?')->execute([$id]);
                    $pdo->prepare('DELETE FROM zone_polygons WHERE zone_id=?')->execute([$id]);
                    $pdo->prepare('DELETE FROM zones WHERE id=?')->execute([$id]);
                    $msg = 'Зона удалена';
                }
                break;
            case 'delete_vehicle':
                $id = (int)($_POST['id'] ?? 0);
                if ($id) {
                    $pdo->prepare('DELETE FROM vehicle_zones WHERE vehicle_id=?')->execute([$id]);
                    $pdo->prepare('DELETE FROM vehicles WHERE id=?')->execute([$id]);
                    $msg = 'Машина удалена';
                }
                break;
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$zones = $pdo->query('SELECT * FROM zones ORDER BY sort_order, name')->fetchAll();
$vehicles = $pdo->query('SELECT * FROM vehicles ORDER BY name')->fetchAll();
$binds = $pdo->query(
    'SELECT vz.*, v.name AS vname, z.name AS zname FROM vehicle_zones vz
     JOIN vehicles v ON v.id = vz.vehicle_id JOIN zones z ON z.id = vz.zone_id'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Админка — Логистика</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="../assets/js/theme.js"></script>
</head>
<body>
<div class="app">
  <div class="admin-nav">
    <a href="index.php"><strong>Админка</strong></a>
    <a href="users.php">Пользователи и роли</a>
    <a href="settings.php">Настройки API</a>
    <a href="../index.php">Рабочий стол</a>
    <a href="../logout.php">Выйти</a>
  </div>

  <h1>Админка</h1>
  <?php if ($msg): ?><div class="flash flash-ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash flash-err"><?= h($err) ?></div><?php endif; ?>

  <div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:16px">
    <section class="panel">
      <h2>Зоны</h2>
      <form method="post" style="display:grid;gap:6px;margin-bottom:12px">
        <input type="hidden" name="action" value="add_zone">
        <input name="name" placeholder="Название" required>
        <input name="code" placeholder="Код">
        <input name="sort_order" type="number" value="0" placeholder="Порядок">
        <button class="btn btn-primary" type="submit">Добавить зону</button>
      </form>
      <ul style="list-style:none;padding:0">
        <?php foreach ($zones as $z): ?>
        <li style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid var(--border)">
          <span><?= h($z['name']) ?></span>
          <form method="post" onsubmit="return confirm('Удалить зону?')">
            <input type="hidden" name="action" value="delete_zone">
            <input type="hidden" name="id" value="<?= (int)$z['id'] ?>">
            <button class="btn-del" type="submit">×</button>
          </form>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="panel">
      <h2>Машины</h2>
      <form method="post" style="display:grid;gap:6px;margin-bottom:12px">
        <input type="hidden" name="action" value="add_vehicle">
        <input name="name" placeholder="Название" required>
        <input name="plate" placeholder="Госномер">
        <input name="capacity_kg" type="number" step="1" value="6500" placeholder="Грузоподъёмность кг">
        <button class="btn btn-primary" type="submit">Добавить машину</button>
      </form>
      <ul style="list-style:none;padding:0">
        <?php foreach ($vehicles as $v): ?>
        <li style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid var(--border)">
          <span><?= h($v['name']) ?><?= $v['plate'] ? ' · '.h($v['plate']) : '' ?> (<?= (int)$v['capacity_kg'] ?> кг)</span>
          <form method="post" onsubmit="return confirm('Удалить?')">
            <input type="hidden" name="action" value="delete_vehicle">
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <button class="btn-del" type="submit">×</button>
          </form>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="panel">
      <h2>Привязки машина → зона</h2>
      <form method="post" style="display:grid;gap:6px;margin-bottom:12px">
        <input type="hidden" name="action" value="bind">
        <select name="vehicle_id" required>
          <option value="">Машина</option>
          <?php foreach ($vehicles as $v): ?>
          <option value="<?= (int)$v['id'] ?>"><?= h($v['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="zone_id" required>
          <option value="">Зона</option>
          <?php foreach ($zones as $z): ?>
          <option value="<?= (int)$z['id'] ?>"><?= h($z['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit">Привязать</button>
      </form>
      <ul style="list-style:none;padding:0">
        <?php foreach ($binds as $b): ?>
        <li style="padding:6px 0;border-bottom:1px solid var(--border)">
          <?= h($b['vname']) ?> → <?= h($b['zname']) ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>

  <section class="panel" style="margin-top:16px">
    <h2>Доступ</h2>
    <p class="muted">
      <a href="users.php">Пользователи и роли</a> — водители (машина), торговые (зона), диспетчеры, админы.<br>
      <a href="settings.php">Настройки API</a> — ключ 1С, Яндекс.Карты, DaData.
    </p>
  </section>
</div>
</body>
</html>
