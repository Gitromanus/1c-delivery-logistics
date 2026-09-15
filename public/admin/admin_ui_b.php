<?php if ($tab === 'settings'): ?>
  <section class="panel" style="max-width:560px">
    <h2>Ключи API</h2>
    <form method="post" style="display:grid;gap:10px">
      <input type="hidden" name="action" value="save_settings">
      <label>API-ключ для 1С (X-Api-Key)<input type="text" name="api_key" value="<?= htmlspecialchars($apiKey) ?>" autocomplete="off" style="width:100%"></label>
      <label>Yandex Maps JS API<input type="text" name="yandex_maps_key" value="<?= htmlspecialchars($ymKey) ?>" autocomplete="off" style="width:100%"></label>
      <label>Yandex Geocoder HTTP<input type="text" name="yandex_geocoder_key" value="<?= htmlspecialchars($ygKey) ?>" autocomplete="off" style="width:100%"></label>
      <label>DaData token<input type="text" name="dadata_token" value="<?= htmlspecialchars($ddKey) ?>" autocomplete="off" style="width:100%"></label>
      <button class="btn btn-primary" type="submit">Сохранить</button>
    </form>
    <p class="muted" style="margin-top:12px;font-size:0.85rem">Значения в <code>app_settings</code> перекрывают config.php. Если таблиц нет — /seed_admin.php</p>
  </section>
<?php endif; ?>
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
<div class="modal-overlay" id="userModal" onclick="if(event.target===this)closeModal('userModal')">
  <form method="post" class="modal" onsubmit="closeModal('userModal')">
    <h3 id="userModalTitle">Добавить пользователя</h3>
    <input type="hidden" name="action" id="userAction" value="add_user">
    <input type="hidden" name="id" id="userId" value="">
    <label>Логин</label><input type="text" name="login" id="userLogin" required>
    <label>Пароль <span class="muted" id="userPassHint"></span></label><input type="password" name="password" id="userPass">
    <label>Имя</label><input type="text" name="name" id="userName">
    <label>Роль</label>
    <select name="role" id="userRole" onchange="toggleUserRoleFields()">
      <option value="dispatcher">Диспетчер</option>
      <option value="driver">Водитель</option>
      <option value="sales">Торговый</option>
      <option value="admin">Админ</option>
    </select>
    <label id="userVehLabel">Машина (водитель)</label>
    <select name="vehicle_id" id="userVehicle"><option value="">—</option>
      <?php foreach ($vehicles as $v): ?><option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option><?php endforeach; ?>
    </select>
    <label id="userZoneLabel">Зона (торговый)</label>
    <select name="zone_id" id="userZone"><option value="">—</option>
      <?php foreach ($zones as $z): ?><option value="<?= (int)$z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option><?php endforeach; ?>
    </select>
    <label id="userActiveLabel" style="display:none;align-items:center;gap:8px;margin-top:12px">
      <input type="checkbox" name="is_active" id="userActive" value="1" checked> Активен
    </label>
    <div class="modal-actions"><button class="btn btn-ghost" type="button" onclick="closeModal('userModal')">Отмена</button>
    <button class="btn btn-primary" type="submit" id="userModalSubmit">Добавить</button></div>
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
function toggleUserRoleFields(){var r=document.getElementById('userRole').value;document.getElementById('userVehLabel').style.display=r==='driver'?'':'none';document.getElementById('userVehicle').style.display=r==='driver'?'':'none';document.getElementById('userZoneLabel').style.display=r==='sales'?'':'none';document.getElementById('userZone').style.display=r==='sales'?'':'none';}
function openUserAdd(){document.getElementById('userAction').value='add_user';document.getElementById('userId').value='';document.getElementById('userLogin').value='';document.getElementById('userLogin').readOnly=false;document.getElementById('userPass').value='';document.getElementById('userPass').required=true;document.getElementById('userPassHint').textContent='';document.getElementById('userName').value='';document.getElementById('userRole').value='dispatcher';document.getElementById('userVehicle').value='';document.getElementById('userZone').value='';document.getElementById('userActiveLabel').style.display='none';document.getElementById('userModalTitle').textContent='Добавить пользователя';document.getElementById('userModalSubmit').textContent='Добавить';toggleUserRoleFields();openModal('userModal');}
function editUser(id,login,name,role,vid,zid,active){document.getElementById('userAction').value='edit_user';document.getElementById('userId').value=id;document.getElementById('userLogin').value=login;document.getElementById('userLogin').readOnly=true;document.getElementById('userPass').value='';document.getElementById('userPass').required=false;document.getElementById('userPassHint').textContent='(пусто = не менять)';document.getElementById('userName').value=name||'';document.getElementById('userRole').value=role;document.getElementById('userVehicle').value=vid||'';document.getElementById('userZone').value=zid||'';document.getElementById('userActive').checked=!!active;document.getElementById('userActiveLabel').style.display='flex';document.getElementById('userModalTitle').textContent='Изменить пользователя';document.getElementById('userModalSubmit').textContent='Сохранить';toggleUserRoleFields();openModal('userModal');}
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
  if(!document.getElementById('zpMap')) return;
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
    var st=document.getElementById('zpStatus');
    if(!confirm('Удалить полигон этой зоны?')) return;
    try{
      var r=await fetch('../api/save_zone_polygon.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({zone_id:zpZoneId(),action:'delete'})});
      var d=await r.json();
      if(!d.ok) throw new Error(d.error||'error');
      delete zpPolyMap[zpZoneId()];
      if(zpPolygon){zpMap.geoObjects.remove(zpPolygon);zpPolygon=null;}
      zpRenderGhosts(zpZoneId());
      st.textContent='Удалено';
    }catch(e){alert('Ошибка: '+e.message);st.textContent='';}
  });
});
</script>
<?php endif; ?>
<script src="../assets/js/admin-settings.js?v=8"></script>
</body>
</html>
