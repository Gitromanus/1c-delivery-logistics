<?php
if (class_exists('Auth')) {
    Auth::requireAdmin('../login.php');
}
require dirname(__DIR__) . '/bootstrap.php';
$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';

// Redirect if only password form needed - Auth handles login
if (!class_exists('Auth') && empty($_SESSION['admin_ok'])) {
    if (isset($_POST['password'])) {
        if (hash_equals((string)($config['admin_password'] ?? ''), (string)$_POST['password'])) {
            $_SESSION['admin_ok'] = true;
            header('Location: index.php');
            exit;
        }
        $error = 'Неверный пароль';
    }
    ?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Админка</title>
<link rel="stylesheet" href="../assets/css/style.css"></head><body>
<div class="app" style="max-width:400px"><h1>Админка</h1>
<?php if (!empty($error)): ?><div class="flash flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="panel"><label>Пароль</label><br>
<input type="password" name="password" style="width:100%;margin:8px 0" required>
<button class="btn btn-primary" type="submit">Войти</button></form></div></body></html>
    <?php
    exit;
}

$pdo = Database::pdo();
$msg = '';
// Handle zone/vehicle/bind POST actions briefly - full CRUD in original
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_zone') {
            $pdo->prepare('INSERT INTO zones (name, code, sort_order, is_active) VALUES (?,?,?,1)')
                ->execute([trim($_POST['name']??''), trim($_POST['code']??''), (int)($_POST['sort_order']??0)]);
            $msg = 'Зона добавлена';
        } elseif ($action === 'edit_zone') {
            $pdo->prepare('UPDATE zones SET name=?, code=?, sort_order=? WHERE id=?')
                ->execute([trim($_POST['name']??''), trim($_POST['code']??''), (int)($_POST['sort_order']??0), (int)$_POST['id']]);
            $msg = 'Зона обновлена';
        } elseif ($action === 'delete_zone') {
            $id = (int)$_POST['id'];
            $pdo->prepare('DELETE FROM zone_polygons WHERE zone_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM vehicle_zones WHERE zone_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM zones WHERE id=?')->execute([$id]);
            $msg = 'Зона удалена';
        } elseif ($action === 'add_vehicle') {
            $pdo->prepare('INSERT INTO vehicles (name, plate, capacity_kg, is_active) VALUES (?,?,?,1)')
                ->execute([trim($_POST['name']??''), trim($_POST['plate']??''), (float)($_POST['capacity_kg']??900)]);
            $msg = 'Машина добавлена';
        } elseif ($action === 'edit_vehicle') {
            $pdo->prepare('UPDATE vehicles SET name=?, plate=?, capacity_kg=? WHERE id=?')
                ->execute([trim($_POST['name']??''), trim($_POST['plate']??''), (float)($_POST['capacity_kg']??900), (int)$_POST['id']]);
            $msg = 'Машина обновлена';
        } elseif ($action === 'delete_vehicle') {
            $id = (int)$_POST['id'];
            $pdo->prepare('DELETE FROM vehicle_zones WHERE vehicle_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM vehicles WHERE id=?')->execute([$id]);
            $msg = 'Машина удалена';
        } elseif ($action === 'bind') {
            $pdo->prepare('INSERT INTO vehicle_zones (vehicle_id, zone_id, is_primary) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_primary=VALUES(is_primary)')
                ->execute([(int)$_POST['vehicle_id'], (int)$_POST['zone_id'], !empty($_POST['is_primary'])?1:0]);
            $msg = 'Привязка сохранена';
        } elseif ($action === 'unbind') {
            $pdo->prepare('DELETE FROM vehicle_zones WHERE vehicle_id=? AND zone_id=?')
                ->execute([(int)$_POST['vehicle_id'], (int)$_POST['zone_id']]);
            $msg = 'Привязка удалена';
        }
    } catch (Throwable $e) { $msg = 'Ошибка: '.$e->getMessage(); }
}

$zones = $pdo->query("SELECT z.*, zp.color AS poly_color FROM zones z LEFT JOIN zone_polygons zp ON zp.zone_id = z.id WHERE z.is_active = 1 ORDER BY z.sort_order, z.name")->fetchAll();
$vehicles = $pdo->query('SELECT * FROM vehicles WHERE is_active = 1 ORDER BY name')->fetchAll();
$binds = $pdo->query('SELECT vz.*, v.name AS vehicle_name, z.name AS zone_name FROM vehicle_zones vz JOIN vehicles v ON v.id = vz.vehicle_id JOIN zones z ON z.id = vz.zone_id')->fetchAll();
$zonePolys = $pdo->query('SELECT zone_id, polygon, color FROM zone_polygons')->fetchAll();
$polyMap = [];
foreach ($zonePolys as $zp) {
    $polyMap[(int)$zp['zone_id']] = ['points' => json_decode((string)$zp['polygon'], true) ?: [], 'color' => (string)($zp['color'] ?? '')];
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
      <a class="btn btn-ghost" href="users.php">Пользователи</a>
      <a class="btn btn-ghost" href="settings.php">Настройки API</a>
      <a class="btn btn-ghost" href="../">Рабочий стол</a>
      <a class="btn btn-ghost" href="../logout.php">Выйти</a>
    </div>
  </header>
  <?php if ($msg): ?><div class="flash flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <div class="grid" style="grid-template-columns:1fr 1fr 1fr">
    <section class="panel">
      <div class="panel-head"><h2>Зоны</h2>
        <button class="btn btn-ghost btn-sm" type="button" onclick="openZoneAdd()">+ Добавить</button></div>
      <table><tr><th>Название</th><th></th></tr>
        <?php foreach ($zones as $z): ?>
          <tr>
            <td><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:<?= htmlspecialchars(!empty($z['poly_color']) ? $z['poly_color'] : '#ccc') ?>;margin-right:6px;vertical-align:middle"></span><?= htmlspecialchars($z['name']) ?></td>
            <td style="text-align:right;white-space:nowrap">
              <button type="button" class="btn-icon" onclick="editZone(<?= (int)$z['id'] ?>, '<?= htmlspecialchars((string)$z['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string)$z['code'], ENT_QUOTES) ?>', <?= (int)$z['sort_order'] ?>)">✎</button>
              <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')">
                <input type="hidden" name="action" value="delete_zone"><input type="hidden" name="id" value="<?= (int)$z['id'] ?>">
                <button class="btn-del">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Машины</h2>
        <button class="btn btn-ghost btn-sm" type="button" onclick="openVehicleAdd()">+ Добавить</button></div>
      <table><tr><th>Название</th><th>кг</th><th></th></tr>
        <?php foreach ($vehicles as $v): ?>
          <tr>
            <td><?= htmlspecialchars($v['name']) ?><?= $v['plate'] ? ' · '.htmlspecialchars($v['plate']) : '' ?></td>
            <td><?= number_format((float)$v['capacity_kg'], 0, '.', ' ') ?></td>
            <td style="text-align:right;white-space:nowrap">
              <button type="button" class="btn-icon" onclick="editVehicle(<?= (int)$v['id'] ?>, '<?= htmlspecialchars($v['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string)$v['plate'], ENT_QUOTES) ?>', <?= (float)$v['capacity_kg'] ?>)">✎</button>
              <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')">
                <input type="hidden" name="action" value="delete_vehicle"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                <button class="btn-del">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Привязки</h2>
        <button class="btn btn-ghost btn-sm" type="button" onclick="openBindAdd()">+ Привязать</button></div>
      <table><tr><th>Машина</th><th>Зона</th><th></th></tr>
        <?php foreach ($binds as $b): ?>
          <tr>
            <td><?= htmlspecialchars($b['vehicle_name']) ?></td>
            <td><?= htmlspecialchars($b['zone_name']) ?><?= $b['is_primary'] ? ' ★' : '' ?></td>
            <td style="text-align:right">
              <form method="post" style="display:inline" onsubmit="return confirm('Отвязать?')">
                <input type="hidden" name="action" value="unbind">
                <input type="hidden" name="vehicle_id" value="<?= (int)$b['vehicle_id'] ?>">
                <input type="hidden" name="zone_id" value="<?= (int)$b['zone_id'] ?>">
                <button class="btn-del">✕</button>
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
      <select id="zpZone"><?php foreach ($zones as $z): ?><option value="<?= (int)$z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option><?php endforeach; ?></select>
      <input type="color" id="zpColor" value="#1a73e8" title="Цвет">
      <button class="btn btn-primary" type="button" id="zpDraw">Рисовать заново</button>
      <button class="btn btn-ghost" type="button" id="zpEdit">Редактировать</button>
      <button class="btn btn-ghost" type="button" id="zpSave">Сохранить</button>
      <button class="btn btn-ghost" type="button" id="zpDelete">Удалить</button>
      <span class="muted" id="zpStatus"></span>
    </div>
    <?php if (empty($config['yandex_maps_key'])): ?>
      <p class="muted">Укажите ключ в <a href="settings.php">Настройках API</a></p>
    <?php else: ?>
      <div id="zpMap" class="map-box" style="height:480px"></div>
      <p class="muted" style="margin-top:8px">Выберите зону → «Рисовать заново»: кликами вершины, двойной клик завершает → «Сохранить».</p>
    <?php endif; ?>
  </section>
</div>
<div class="modal-overlay" id="zoneModal" onclick="if(event.target===this)closeModal('zoneModal')">
  <form method="post" class="modal" onsubmit="closeModal('zoneModal')">
    <h3 id="zoneModalTitle">Добавить зону</h3>
    <input type="hidden" name="action" id="zoneAction" value="add_zone">
    <input type="hidden" name="id" id="zoneId" value="">
    <label>Название</label><input type="text" name="name" id="zoneName" required>
    <label>Код</label><input type="text" name="code" id="zoneCode">
    <label>Порядок</label><input type="number" name="sort_order" id="zoneSort" value="0">
    <div class="modal-actions"><button class="btn btn-ghost" type="button" onclick="closeModal('zoneModal')">Отмена</button>
    <button class="btn btn-primary" type="submit" id="zoneModalSubmit">Добавить</button></div>
  </form>
</div>
<div class="modal-overlay" id="vehicleModal" onclick="if(event.target===this)closeModal('vehicleModal')">
  <form method="post" class="modal" onsubmit="closeModal('vehicleModal')">
    <h3 id="vehicleModalTitle">Добавить машину</h3>
    <input type="hidden" name="action" id="vehicleAction" value="add_vehicle">
    <input type="hidden" name="id" id="vehicleId" value="">
    <label>Название</label><input type="text" name="name" id="vehicleName" required>
    <label>Госномер</label><input type="text" name="plate" id="vehiclePlate">
    <label>Грузоподъёмность, кг</label><input type="number" step="0.01" name="capacity_kg" id="vehicleCap" value="900">
    <div class="modal-actions"><button class="btn btn-ghost" type="button" onclick="closeModal('vehicleModal')">Отмена</button>
    <button class="btn btn-primary" type="submit" id="vehicleModalSubmit">Добавить</button></div>
  </form>
</div>
<div class="modal-overlay" id="bindModal" onclick="if(event.target===this)closeModal('bindModal')">
  <form method="post" class="modal" onsubmit="closeModal('bindModal')">
    <h3>Привязать машину к зоне</h3>
    <input type="hidden" name="action" value="bind">
    <label>Машина</label><select name="vehicle_id" id="bindVehicle"><?php foreach ($vehicles as $v): ?><option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option><?php endforeach; ?></select>
    <label>Зона</label><select name="zone_id" id="bindZone"><?php foreach ($zones as $z): ?><option value="<?= (int)$z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option><?php endforeach; ?></select>
    <label style="display:flex;align-items:center;gap:8px;margin-top:12px"><input type="checkbox" name="is_primary" value="1" checked> Основная зона</label>
    <div class="modal-actions"><button class="btn btn-ghost" type="button" onclick="closeModal('bindModal')">Отмена</button>
    <button class="btn btn-primary" type="submit">Привязать</button></div>
  </form>
</div>
<script>
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
function openZoneAdd(){document.getElementById('zoneAction').value='add_zone';document.getElementById('zoneId').value='';document.getElementById('zoneName').value='';document.getElementById('zoneCode').value='';document.getElementById('zoneSort').value='0';document.getElementById('zoneModalTitle').textContent='Добавить зону';document.getElementById('zoneModalSubmit').textContent='Добавить';openModal('zoneModal');}
function editZone(id,name,code,sort){document.getElementById('zoneAction').value='edit_zone';document.getElementById('zoneId').value=id;document.getElementById('zoneName').value=name;document.getElementById('zoneCode').value=code||'';document.getElementById('zoneSort').value=sort;document.getElementById('zoneModalTitle').textContent='Изменить зону';document.getElementById('zoneModalSubmit').textContent='Сохранить';openModal('zoneModal');}
function openVehicleAdd(){document.getElementById('vehicleAction').value='add_vehicle';document.getElementById('vehicleId').value='';document.getElementById('vehicleName').value='';document.getElementById('vehiclePlate').value='';document.getElementById('vehicleCap').value='900';document.getElementById('vehicleModalTitle').textContent='Добавить машину';document.getElementById('vehicleModalSubmit').textContent='Добавить';openModal('vehicleModal');}
function editVehicle(id,name,plate,cap){document.getElementById('vehicleAction').value='edit_vehicle';document.getElementById('vehicleId').value=id;document.getElementById('vehicleName').value=name;document.getElementById('vehiclePlate').value=plate||'';document.getElementById('vehicleCap').value=cap;document.getElementById('vehicleModalTitle').textContent='Изменить машину';document.getElementById('vehicleModalSubmit').textContent='Сохранить';openModal('vehicleModal');}
function openBindAdd(){openModal('bindModal');}
</script>
<?php if (!empty($config['yandex_maps_key'])): ?>
<script>
const zpPolyMap = <?= json_encode($polyMap, JSON_UNESCAPED_UNICODE) ?>;
let zpMap, zpPolygon, zpEditorActive=false;
function zpZoneId(){return parseInt(document.getElementById('zpZone').value,10);}
function zpZoneColor(){return document.getElementById('zpColor').value||'#1a73e8';}
function zpRenderGhosts(exceptId){
  if(!zpMap)return;
  zpMap.geoObjects.removeAll();
  Object.keys(zpPolyMap).forEach(function(id){
    if(parseInt(id,10)===exceptId)return;
    var zp=zpPolyMap[id]; if(!zp||!zp.points||zp.points.length<3)return;
    var color=zp.color||'#1a73e8';
    zpMap.geoObjects.add(new ymaps.Polygon([zp.points],{hintContent:'Зона #'+id},{fillColor:color,fillOpacity:0.06,strokeColor:color,strokeWidth:1.5,strokeOpacity:0.5}));
  });
  if(zpPolygon) zpMap.geoObjects.add(zpPolygon);
}
function zpLoadCurrent(){
  var saved=zpPolyMap[zpZoneId()];
  if(zpPolygon){zpMap.geoObjects.remove(zpPolygon);zpPolygon=null;}
  if(saved&&saved.points&&saved.points.length>=3){
    var color=(saved.color)?saved.color:zpZoneColor();
    if(saved.color) document.getElementById('zpColor').value=saved.color;
    zpPolygon=new ymaps.Polygon([saved.points],{},{fillColor:color,fillOpacity:0.15,strokeColor:color,strokeWidth:2});
    zpMap.geoObjects.add(zpPolygon);
  }
  zpRenderGhosts(zpZoneId());
}
ymaps.ready(function(){
  zpMap=new ymaps.Map('zpMap',{center:[47.411,40.091],zoom:9,controls:['zoomControl','typeSelector']});
  zpLoadCurrent();
  document.getElementById('zpZone').addEventListener('change',zpLoadCurrent);
  document.getElementById('zpDraw').addEventListener('click',function(){
    if(zpPolygon){zpMap.geoObjects.remove(zpPolygon);zpPolygon=null;}
    zpPolygon=new ymaps.Polygon([[]]);
    zpMap.geoObjects.add(zpPolygon);
    zpPolygon.editor.startDrawing();
    zpEditorActive=true;
    zpPolygon.editor.events.once('drawingstop',function(){zpEditorActive=false;});
  });
  document.getElementById('zpEdit').addEventListener('click',function(){
    if(!zpPolygon){alert('Нет полигона');return;}
    zpPolygon.editor.startEditing();
  });
  document.getElementById('zpSave').addEventListener('click',async function(){
    var st=document.getElementById('zpStatus');
    if(!zpPolygon){alert('Нет полигона');return;}
    var pts=zpPolygon.geometry.getCoordinates()[0];
    if(!pts||pts.length<3){alert('Нужно минимум 3 точки');return;}
    st.textContent='Сохранение…';
    try{
      var r=await fetch('../api/save_zone_polygon.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({zone_id:zpZoneId(),polygon:pts,color:zpZoneColor(),action:'save'})});
      var d=await r.json();
      if(!d.ok) throw new Error(d.error||'error');
      zpPolyMap[zpZoneId()]={points:pts,color:zpZoneColor()};
      zpRenderGhosts(zpZoneId());
      st.textContent='Сохранено ('+pts.length+' точек)';
    }catch(e){alert('Ошибка: '+e.message);st.textContent='';}
  });
  document.getElementById('zpDelete').addEventListener('click',async function(){
    if(!confirm('Удалить полигон?'))return;
    try{
      var r=await fetch('../api/save_zone_polygon.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({zone_id:zpZoneId(),action:'delete'})});
      var d=await r.json();
      if(!d.ok) throw new Error(d.error||'error');
      delete zpPolyMap[zpZoneId()];
      if(zpPolygon){zpMap.geoObjects.remove(zpPolygon);zpPolygon=null;}
      zpRenderGhosts(zpZoneId());
      document.getElementById('zpStatus').textContent='Удалено';
    }catch(e){alert('Ошибка: '+e.message);}
  });
});
</script>
<?php endif; ?>
<script src="../assets/js/theme.js"></script>
<script src="../assets/js/admin-settings.js?v=8"></script>
</body>
</html>
