<?php
session_start();
require dirname(__DIR__) . '/bootstrap.php';
$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';

$error = '';
if (isset($_POST['password'])) {
    if (hash_equals((string) $config['admin_password'], (string) $_POST['password'])) {
        $_SESSION['admin_ok'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Неверный пароль';
}
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_ok']);
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['admin_ok'])): ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админка — вход</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app" style="max-width:400px">
  <h1 class="logo" style="margin-bottom:16px">Админка логистики</h1>
  <?php if ($error): ?><div class="flash flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" class="panel">
    <label class="muted">Пароль</label><br>
    <input type="password" name="password" style="width:100%;margin:8px 0 12px" required>
    <button class="btn btn-primary" type="submit">Войти</button>
  </form>
  <p class="muted" style="margin-top:12px"><a href="../">← На рабочий стол</a></p>
</div>
</body>
</html>
<?php exit; endif;

$pdo = Database::pdo();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_zone') {
        $pdo->prepare('INSERT INTO zones (name, code, keywords, sort_order) VALUES (?,?,?,?)')
            ->execute([
                trim($_POST['name'] ?? ''),
                trim($_POST['code'] ?? '') ?: null,
                trim($_POST['keywords'] ?? '') ?: null,
                (int) ($_POST['sort_order'] ?? 0),
            ]);
        $msg = 'Зона добавлена';
    }
    if ($_POST['action'] === 'add_vehicle') {
        $pdo->prepare('INSERT INTO vehicles (name, plate, capacity_kg) VALUES (?,?,?)')
            ->execute([
                trim($_POST['name'] ?? ''),
                trim($_POST['plate'] ?? '') ?: null,
                (float) ($_POST['capacity_kg'] ?? 1000),
            ]);
        $msg = 'Машина добавлена';
    }
    if ($_POST['action'] === 'bind') {
        $pdo->prepare('INSERT IGNORE INTO vehicle_zones (vehicle_id, zone_id, is_primary) VALUES (?,?,?)')
            ->execute([
                (int) $_POST['vehicle_id'],
                (int) $_POST['zone_id'],
                !empty($_POST['is_primary']) ? 1 : 0,
            ]);
        $msg = 'Привязка сохранена';
    }
}

$zones = $pdo->query('SELECT * FROM zones ORDER BY sort_order, name')->fetchAll();
$vehicles = $pdo->query('SELECT * FROM vehicles ORDER BY name')->fetchAll();
$binds = $pdo->query(
    'SELECT vz.*, v.name AS vname, z.name AS zname FROM vehicle_zones vz
     JOIN vehicles v ON v.id = vz.vehicle_id
     JOIN zones z ON z.id = vz.zone_id'
)->fetchAll();

// Полигоны зон
$zonePolys = $pdo->query('SELECT zone_id, polygon, color FROM zone_polygons')->fetchAll();
$polyMap = [];
foreach ($zonePolys as $zp) {
    $polyMap[(int) $zp['zone_id']] = [
        'points' => json_decode((string) $zp['polygon'], true) ?: [],
        'color' => (string) ($zp['color'] ?? ''),
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админка логистики</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <?php if (!empty($config['yandex_maps_key'])): ?>
  <script src="https://api-maps.yandex.ru/2.1/?apikey=<?= htmlspecialchars($config['yandex_maps_key']) ?>&lang=ru_RU"></script>
  <?php endif; ?>
</head>
<body>
<div class="app">
  <header class="header">
    <div class="logo">Админка</div>
    <div class="toolbar">
      <a class="btn btn-ghost" href="../">Рабочий стол</a>
      <a class="btn btn-ghost" href="?logout=1">Выйти</a>
    </div>
  </header>

  <?php if ($msg): ?><div class="flash flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="admin-nav muted">API: POST /api/orders.php · ключ в config.php (X-Api-Key)</div>

  <div class="grid" style="grid-template-columns:1fr 1fr">
    <section class="panel">
      <h2>Зоны</h2>
      <table>
        <tr><th>Название</th><th>Ключевые слова</th></tr>
        <?php foreach ($zones as $z): ?>
          <tr>
            <td><?= htmlspecialchars($z['name']) ?></td>
            <td class="muted"><?= htmlspecialchars((string) $z['keywords']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <form method="post" style="margin-top:14px">
        <input type="hidden" name="action" value="add_zone">
        <input type="text" name="name" placeholder="Название" required style="width:100%;margin-bottom:8px">
        <input type="text" name="keywords" placeholder="Ключевые слова: Молодёжн;Молодежн" style="width:100%;margin-bottom:8px">
        <button class="btn btn-primary" type="submit">Добавить зону</button>
      </form>
    </section>

    <section class="panel">
      <h2>Машины</h2>
      <table>
        <tr><th>Название</th><th>Номер</th><th>Кг</th></tr>
        <?php foreach ($vehicles as $v): ?>
          <tr>
            <td><?= htmlspecialchars($v['name']) ?></td>
            <td><?= htmlspecialchars((string) $v['plate']) ?></td>
            <td><?= htmlspecialchars((string) $v['capacity_kg']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <form method="post" style="margin-top:14px">
        <input type="hidden" name="action" value="add_vehicle">
        <input type="text" name="name" placeholder="Газель …" required style="width:100%;margin-bottom:8px">
        <input type="text" name="plate" placeholder="Госномер" style="width:100%;margin-bottom:8px">
        <input type="number" step="0.01" name="capacity_kg" value="900" style="width:100%;margin-bottom:8px">
        <button class="btn btn-primary" type="submit">Добавить машину</button>
      </form>
    </section>
  </div>

  <section class="panel" style="margin-top:16px">
    <h2>Привязка ТС → зона</h2>
    <table>
      <tr><th>Машина</th><th>Зона</th><th>Основная</th></tr>
      <?php foreach ($binds as $b): ?>
        <tr>
          <td><?= htmlspecialchars($b['vname']) ?></td>
          <td><?= htmlspecialchars($b['zname']) ?></td>
          <td><?= $b['is_primary'] ? 'да' : 'резерв' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <form method="post" style="margin-top:14px;display:flex;flex-wrap:wrap;gap:8px;align-items:end">
      <input type="hidden" name="action" value="bind">
      <select name="vehicle_id">
        <?php foreach ($vehicles as $v): ?>
          <option value="<?= (int) $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="zone_id">
        <?php foreach ($zones as $z): ?>
          <option value="<?= (int) $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="muted"><input type="checkbox" name="is_primary" value="1" checked> основная</label>
      <button class="btn btn-primary" type="submit">Привязать</button>
    </form>
  </section>

  <section class="panel" style="margin-top:16px">
    <h2>Полигоны зон</h2>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px">
      <label class="muted">Зона</label>
      <select id="zpZone">
        <?php foreach ($zones as $z): ?>
          <option value="<?= (int) $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="color" id="zpColor" value="#1a73e8" title="Цвет">
      <button class="btn btn-primary" type="button" id="zpDraw">Рисовать заново</button>
      <button class="btn btn-ghost" type="button" id="zpEdit">Редактировать</button>
      <button class="btn btn-ghost" type="button" id="zpSave">Сохранить</button>
      <button class="btn btn-ghost" type="button" id="zpDelete">Удалить</button>
      <span class="muted" id="zpStatus"></span>
    </div>
    <?php if (empty($config['yandex_maps_key'])): ?>
      <p class="muted">Укажите <code>yandex_maps_key</code> в config.php</p>
    <?php else: ?>
      <div id="zpMap" class="map-box" style="height:480px"></div>
      <p class="muted" style="margin-top:8px">
        Выберите зону → «Рисовать заново»: кликами ставьте вершины, двойной клик завершает → «Сохранить».
        «Редактировать» правит существующий полигон.
      </p>
    <?php endif; ?>
  </section>
</div>

<?php if (!empty($config['yandex_maps_key'])): ?>
<script>
const zpPolyMap = <?= json_encode($polyMap, JSON_UNESCAPED_UNICODE) ?>;
let zpMap = null, zpPolygon = null, zpEditorActive = false;

function zpZoneId() { return parseInt(document.getElementById('zpZone').value, 10); }
function zpZoneColor() { return document.getElementById('zpColor').value; }

function zpStopEditing() {
  if (zpPolygon && zpEditorActive) { try { zpPolygon.editor.stopEditing(); } catch (e) {} zpEditorActive = false; }
}

function zpLoad(zoneId) {
  if (zpPolygon) { zpMap.geoObjects.remove(zpPolygon); zpPolygon = null; }
  const saved = zpPolyMap[zoneId];
  const pts = (saved && saved.points) ? saved.points : [];
  const color = (saved && saved.color) ? saved.color : zpZoneColor();
  if (pts.length) {
    zpPolygon = new ymaps.Polygon([pts], {}, {
      fillColor: color, fillOpacity: 0.15, strokeColor: color, strokeWidth: 2
    });
    zpMap.geoObjects.add(zpPolygon);
    try { zpMap.setBounds(zpPolygon.geometry.getBounds(), { checkZoomRange: true, zoomMargin: 20 }); } catch (e) {}
  }
}

ymaps.ready(function () {
  zpMap = new ymaps.Map('zpMap', { center: [47.411, 40.091], zoom: 9, controls: ['zoomControl', 'typeSelector'] });

  document.getElementById('zpZone').addEventListener('change', function () { zpStopEditing(); zpLoad(zpZoneId()); });
  zpLoad(zpZoneId());

  document.getElementById('zpDraw').addEventListener('click', function () {
    zpStopEditing();
    if (zpPolygon) { zpMap.geoObjects.remove(zpPolygon); zpPolygon = null; }
    zpPolygon = new ymaps.Polygon([[]]);
    zpMap.geoObjects.add(zpPolygon);
    zpEditorActive = true;
    zpPolygon.editor.startDrawing();
    zpPolygon.editor.events.once('drawingstop', function () { zpEditorActive = false; });
  });

  document.getElementById('zpEdit').addEventListener('click', function () {
    zpStopEditing();
    if (zpPolygon) { zpEditorActive = true; zpPolygon.editor.startEditing(); }
  });

  document.getElementById('zpSave').addEventListener('click', async function () {
    if (!zpPolygon) { alert('Сначала нарисуйте полигон'); return; }
    zpStopEditing();
    let rings;
    try { rings = zpPolygon.geometry.getCoordinates(); } catch (e) { rings = null; }
    const pts = (rings && rings[0]) ? rings[0] : [];
    if (pts.length < 3) { alert('Мало точек'); return; }
    const st = document.getElementById('zpStatus');
    const body = JSON.stringify({ zone_id: zpZoneId(), polygon: pts, color: zpZoneColor(), action: 'save' });
    st.textContent = 'Сохранение…';
    try {
      const r = await fetch('../api/save_zone_polygon.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body });
      const d = await r.json();
      if (!d.ok) throw new Error(d.error || 'error');
      zpPolyMap[zpZoneId()] = { points: pts, color: zpZoneColor() };
      st.textContent = 'Сохранено (' + pts.length + ' точек)';
    } catch (e) { alert('Ошибка: ' + e.message); st.textContent = ''; }
  });

  document.getElementById('zpDelete').addEventListener('click', async function () {
    const st = document.getElementById('zpStatus');
    if (!confirm('Удалить полигон этой зоны?')) return;
    try {
      const r = await fetch('../api/save_zone_polygon.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ zone_id: zpZoneId(), action: 'delete' }) });
      const d = await r.json();
      if (!d.ok) throw new Error(d.error || 'error');
      delete zpPolyMap[zpZoneId()];
      if (zpPolygon) { zpMap.geoObjects.remove(zpPolygon); zpPolygon = null; }
      st.textContent = 'Удалено';
    } catch (e) { alert('Ошибка: ' + e.message); st.textContent = ''; }
  });
});
</script>
<?php endif; ?>

</body>
</html>
