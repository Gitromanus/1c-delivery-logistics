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
    switch ($_POST['action']) {
        case 'add_zone':
            $pdo->prepare('INSERT INTO zones (name, code, sort_order) VALUES (?,?,?)')
                ->execute([
                    trim($_POST['name'] ?? ''),
                    trim($_POST['code'] ?? '') ?: null,
                    (int) ($_POST['sort_order'] ?? 0),
                ]);
            $msg = 'Зона добавлена';
            break;

        case 'edit_zone':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare('UPDATE zones SET name=?, code=?, sort_order=? WHERE id=?')
                    ->execute([
                        trim($_POST['name'] ?? ''),
                        trim($_POST['code'] ?? '') ?: null,
                        (int) ($_POST['sort_order'] ?? 0),
                        $id,
                    ]);
                $msg = 'Зона обновлена';
            }
            break;

        case 'add_vehicle':
            $pdo->prepare('INSERT INTO vehicles (name, plate, capacity_kg) VALUES (?,?,?)')
                ->execute([
                    trim($_POST['name'] ?? ''),
                    trim($_POST['plate'] ?? '') ?: null,
                    (float) ($_POST['capacity_kg'] ?? 1000),
                ]);
            $msg = 'Машина добавлена';
            break;

        case 'edit_vehicle':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare('UPDATE vehicles SET name=?, plate=?, capacity_kg=? WHERE id=?')
                    ->execute([
                        trim($_POST['name'] ?? ''),
                        trim($_POST['plate'] ?? '') ?: null,
                        (float) ($_POST['capacity_kg'] ?? 1000),
                        $id,
                    ]);
                $msg = 'Машина обновлена';
            }
            break;

        case 'bind':
            $pdo->prepare('INSERT IGNORE INTO vehicle_zones (vehicle_id, zone_id, is_primary) VALUES (?,?,?)')
                ->execute([
                    (int) $_POST['vehicle_id'],
                    (int) $_POST['zone_id'],
                    !empty($_POST['is_primary']) ? 1 : 0,
                ]);
            $msg = 'Привязка сохранена';
            break;

        case 'edit_bind':
            $oldV = (int) ($_POST['old_vehicle_id'] ?? 0);
            $oldZ = (int) ($_POST['old_zone_id'] ?? 0);
            $newV = (int) ($_POST['vehicle_id'] ?? 0);
            $newZ = (int) ($_POST['zone_id'] ?? 0);
            $isPrimary = !empty($_POST['is_primary']) ? 1 : 0;
            if ($oldV === $newV && $oldZ === $newZ) {
                $pdo->prepare('UPDATE vehicle_zones SET is_primary=? WHERE vehicle_id=? AND zone_id=?')
                    ->execute([$isPrimary, $newV, $newZ]);
            } else {
                $pdo->prepare('DELETE FROM vehicle_zones WHERE vehicle_id=? AND zone_id=?')->execute([$oldV, $oldZ]);
                $pdo->prepare('INSERT IGNORE INTO vehicle_zones (vehicle_id, zone_id, is_primary) VALUES (?,?,?)')
                    ->execute([$newV, $newZ, $isPrimary]);
            }
            $msg = 'Привязка обновлена';
            break;

        case 'delete_zone':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare('DELETE FROM vehicle_zones WHERE zone_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM zone_polygons WHERE zone_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM zones WHERE id=?')->execute([$id]);
                $msg = 'Зона удалена';
            }
            break;

        case 'delete_vehicle':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare('DELETE FROM vehicle_zones WHERE vehicle_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM vehicles WHERE id=?')->execute([$id]);
                $msg = 'Машина удалена';
            }
            break;

        case 'delete_bind':
            $pdo->prepare('DELETE FROM vehicle_zones WHERE vehicle_id=? AND zone_id=?')
                ->execute([(int) ($_POST['vehicle_id'] ?? 0), (int) ($_POST['zone_id'] ?? 0)]);
            $msg = 'Привязка удалена';
            break;
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

  <div class="grid" style="grid-template-columns:1fr 1fr 1fr">
    <section class="panel">
      <div class="panel-head">
        <h2>Зоны</h2>
        <button class="btn btn-ghost btn-sm" type="button" onclick="openZoneAdd()">+ Добавить</button>
      </div>
      <table>
        <tr><th>Название</th><th></th></tr>
        <?php foreach ($zones as $z): ?>
          <tr>
            <td><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:<?= htmlspecialchars(!empty($z['poly_color']) ? $z['poly_color'] : '#ccc') ?>;margin-right:6px;vertical-align:middle"></span><?= htmlspecialchars($z['name']) ?></td>
            <td style="text-align:right;white-space:nowrap">
              <button type="button" class="btn-icon" title="Изменить" aria-label="Изменить" onclick="editZone(<?= (int) $z['id'] ?>, '<?= htmlspecialchars((string) $z['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string) $z['code'], ENT_QUOTES) ?>', <?= (int) $z['sort_order'] ?>)">✎</button>
              <form method="post" style="display:inline" onsubmit="return confirm('Удалить зону «<?= htmlspecialchars($z['name'], ENT_QUOTES) ?>» и все её привязки/полигон?')">
                <input type="hidden" name="action" value="delete_zone">
                <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
                <button class="btn-del" title="Удалить" aria-label="Удалить">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2>Машины</h2>
        <button class="btn btn-ghost btn-sm" type="button" onclick="openVehicleAdd()">+ Добавить</button>
      </div>
      <table>
        <tr><th>Название</th><th>Номер</th><th>Кг</th><th></th></tr>
        <?php foreach ($vehicles as $v): ?>
          <tr>
            <td><?= htmlspecialchars($v['name']) ?></td>
            <td><?= htmlspecialchars((string) $v['plate']) ?></td>
            <td><?= htmlspecialchars((string) $v['capacity_kg']) ?></td>
            <td style="text-align:right;white-space:nowrap">
              <button type="button" class="btn-icon" title="Изменить" aria-label="Изменить" onclick="editVehicle(<?= (int) $v['id'] ?>, '<?= htmlspecialchars($v['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string) $v['plate'], ENT_QUOTES) ?>', <?= (float) $v['capacity_kg'] ?>)">✎</button>
              <form method="post" style="display:inline" onsubmit="return confirm('Удалить машину «<?= htmlspecialchars($v['name'], ENT_QUOTES) ?>» и все её привязки?')">
                <input type="hidden" name="action" value="delete_vehicle">
                <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                <button class="btn-del" title="Удалить" aria-label="Удалить">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2>Привязка ТС → зона</h2>
        <button class="btn btn-ghost btn-sm" type="button" onclick="openBindAdd()">+ Добавить</button>
      </div>
      <table>
        <tr><th>Машина</th><th>Зона</th><th>Тип</th><th></th></tr>
        <?php foreach ($binds as $b): ?>
          <tr>
            <td><?= htmlspecialchars($b['vname']) ?></td>
            <td><?= htmlspecialchars($b['zname']) ?></td>
            <td class="muted"><?= $b['is_primary'] ? 'осн.' : 'резерв' ?></td>
            <td style="text-align:right;white-space:nowrap">
              <button type="button" class="btn-icon" title="Изменить" aria-label="Изменить" onclick="editBind(<?= (int) $b['vehicle_id'] ?>, <?= (int) $b['zone_id'] ?>, <?= (int) $b['is_primary'] ?>)">✎</button>
              <form method="post" style="display:inline" onsubmit="return confirm('Удалить привязку «<?= htmlspecialchars($b['vname'], ENT_QUOTES) ?> → <?= htmlspecialchars($b['zname'], ENT_QUOTES) ?>»?')">
                <input type="hidden" name="action" value="delete_bind">
                <input type="hidden" name="vehicle_id" value="<?= (int) $b['vehicle_id'] ?>">
                <input type="hidden" name="zone_id" value="<?= (int) $b['zone_id'] ?>">
                <button class="btn-del" title="Удалить" aria-label="Удалить">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </section>
  </div>

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

<!-- Модалка: добавление зоны -->
<div class="modal-overlay" id="zoneModal" onclick="if(event.target===this)closeModal('zoneModal')">
  <form method="post" class="modal" onsubmit="closeModal('zoneModal')">
    <h3 id="zoneModalTitle">Добавить зону</h3>
    <input type="hidden" name="action" id="zoneAction" value="add_zone">
    <input type="hidden" name="id" id="zoneId" value="">
    <label>Название</label>
    <input type="text" name="name" id="zoneName" placeholder="Например: Ростов-на-Дону" required>
    <label>Код (необязательно)</label>
    <input type="text" name="code" id="zoneCode" placeholder="RND">
    <label>Порядок сортировки</label>
    <input type="number" name="sort_order" id="zoneSort" value="0">
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" onclick="closeModal('zoneModal')">Отмена</button>
      <button class="btn btn-primary" type="submit" id="zoneModalSubmit">Добавить</button>
    </div>
  </form>
</div>

<!-- Модалка: добавление машины -->
<div class="modal-overlay" id="vehicleModal" onclick="if(event.target===this)closeModal('vehicleModal')">
  <form method="post" class="modal" onsubmit="closeModal('vehicleModal')">
    <h3 id="vehicleModalTitle">Добавить машину</h3>
    <input type="hidden" name="action" id="vehicleAction" value="add_vehicle">
    <input type="hidden" name="id" id="vehicleId" value="">
    <label>Название</label>
    <input type="text" name="name" id="vehicleName" placeholder="Газель …" required>
    <label>Госномер</label>
    <input type="text" name="plate" id="vehiclePlate" placeholder="А000АА 161">
    <label>Грузоподъёмность, кг</label>
    <input type="number" step="0.01" name="capacity_kg" id="vehicleCap" value="900">
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" onclick="closeModal('vehicleModal')">Отмена</button>
      <button class="btn btn-primary" type="submit" id="vehicleModalSubmit">Добавить</button>
    </div>
  </form>
</div>

<!-- Модалка: привязка ТС → зона -->
<div class="modal-overlay" id="bindModal" onclick="if(event.target===this)closeModal('bindModal')">
  <form method="post" class="modal" onsubmit="closeModal('bindModal')">
    <h3 id="bindModalTitle">Привязать машину к зоне</h3>
    <input type="hidden" name="action" id="bindAction" value="bind">
    <input type="hidden" name="old_vehicle_id" id="bindOldV" value="">
    <input type="hidden" name="old_zone_id" id="bindOldZ" value="">
    <label>Машина</label>
    <select name="vehicle_id" id="bindVehicle">
      <?php foreach ($vehicles as $v): ?>
        <option value="<?= (int) $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <label>Зона</label>
    <select name="zone_id" id="bindZone">
      <?php foreach ($zones as $z): ?>
        <option value="<?= (int) $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <label style="display:flex;align-items:center;gap:8px;margin-top:12px">
      <input type="checkbox" name="is_primary" id="bindPrimary" value="1" checked> Основная зона
    </label>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" onclick="closeModal('bindModal')">Отмена</button>
      <button class="btn btn-primary" type="submit" id="bindModalSubmit">Привязать</button>
    </div>
  </form>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Зоны
function openZoneAdd() {
  document.getElementById('zoneAction').value = 'add_zone';
  document.getElementById('zoneId').value = '';
  document.getElementById('zoneName').value = '';
  document.getElementById('zoneCode').value = '';
  document.getElementById('zoneSort').value = '0';
  document.getElementById('zoneModalTitle').textContent = 'Добавить зону';
  document.getElementById('zoneModalSubmit').textContent = 'Добавить';
  openModal('zoneModal');
}
function editZone(id, name, code, sort) {
  document.getElementById('zoneAction').value = 'edit_zone';
  document.getElementById('zoneId').value = id;
  document.getElementById('zoneName').value = name;
  document.getElementById('zoneCode').value = code || '';
  document.getElementById('zoneSort').value = sort;
  document.getElementById('zoneModalTitle').textContent = 'Изменить зону';
  document.getElementById('zoneModalSubmit').textContent = 'Сохранить';
  openModal('zoneModal');
}

// Машины
function openVehicleAdd() {
  document.getElementById('vehicleAction').value = 'add_vehicle';
  document.getElementById('vehicleId').value = '';
  document.getElementById('vehicleName').value = '';
  document.getElementById('vehiclePlate').value = '';
  document.getElementById('vehicleCap').value = '900';
  document.getElementById('vehicleModalTitle').textContent = 'Добавить машину';
  document.getElementById('vehicleModalSubmit').textContent = 'Добавить';
  openModal('vehicleModal');
}
function editVehicle(id, name, plate, cap) {
  document.getElementById('vehicleAction').value = 'edit_vehicle';
  document.getElementById('vehicleId').value = id;
  document.getElementById('vehicleName').value = name;
  document.getElementById('vehiclePlate').value = plate || '';
  document.getElementById('vehicleCap').value = cap;
  document.getElementById('vehicleModalTitle').textContent = 'Изменить машину';
  document.getElementById('vehicleModalSubmit').textContent = 'Сохранить';
  openModal('vehicleModal');
}

// Привязки
function openBindAdd() {
  document.getElementById('bindAction').value = 'bind';
  document.getElementById('bindOldV').value = '';
  document.getElementById('bindOldZ').value = '';
  document.getElementById('bindPrimary').checked = true;
  document.getElementById('bindModalTitle').textContent = 'Привязать машину к зоне';
  document.getElementById('bindModalSubmit').textContent = 'Привязать';
  openModal('bindModal');
}
function editBind(oldV, oldZ, isPrimary) {
  document.getElementById('bindAction').value = 'edit_bind';
  document.getElementById('bindOldV').value = oldV;
  document.getElementById('bindOldZ').value = oldZ;
  document.getElementById('bindVehicle').value = String(oldV);
  document.getElementById('bindZone').value = String(oldZ);
  document.getElementById('bindPrimary').checked = !!isPrimary;
  document.getElementById('bindModalTitle').textContent = 'Изменить привязку';
  document.getElementById('bindModalSubmit').textContent = 'Сохранить';
  openModal('bindModal');
}
</script>

<?php if (!empty($config['yandex_maps_key'])): ?>
<script>
const zpPolyMap = <?= json_encode($polyMap, JSON_UNESCAPED_UNICODE) ?>;
let zpMap = null, zpPolygon = null, zpEditorActive = false;
let zpGhosts = []; // силуэты остальных зон

function zpZoneId() { return parseInt(document.getElementById('zpZone').value, 10); }
function zpZoneColor() { return document.getElementById('zpColor').value; }

// Силуэты всех зон (кроме текущей), чтобы видеть границы соседей при рисовании
function zpRenderGhosts(excludeId) {
  zpGhosts.forEach(function (g) { try { zpMap.geoObjects.remove(g); } catch (e) {} });
  zpGhosts = [];
  Object.keys(zpPolyMap).forEach(function (zid) {
    const id = parseInt(zid, 10);
    if (excludeId && id === excludeId) return;
    const zp = zpPolyMap[zid];
    if (!zp.points || !zp.points.length) return;
    const color = zp.color || '#1a73e8';
    const g = new ymaps.Polygon([zp.points], { hintContent: 'Зона #' + id }, {
      fillColor: color, fillOpacity: 0.06,
      strokeColor: color, strokeWidth: 1.5, strokeOpacity: 0.5,
      strokeStyle: 'dash'
    });
    zpMap.geoObjects.add(g);
    zpGhosts.push(g);
  });
}

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

  document.getElementById('zpZone').addEventListener('change', function () { zpStopEditing(); zpRenderGhosts(zpZoneId()); zpLoad(zpZoneId()); });
  zpRenderGhosts(zpZoneId());
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
      zpRenderGhosts(zpZoneId());
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
      zpRenderGhosts(zpZoneId());
      st.textContent = 'Удалено';
    } catch (e) { alert('Ошибка: ' + e.message); st.textContent = ''; }
  });
});
</script>
<?php endif; ?>

</body>
</html>
