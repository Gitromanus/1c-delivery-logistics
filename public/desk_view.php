<?php /* compact desk restored */ ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Логистика доставки</title>
<link rel="stylesheet" href="assets/css/style.css?v=19">
<link rel="stylesheet" href="assets/css/mobile-ui.css?v=2">
<?php if ($yandexKey !== ''): ?>
<script src="https://api-maps.yandex.ru/2.1/?apikey=<?= h($yandexKey) ?>&lang=ru_RU"></script>
<?php endif; ?>
<script src="assets/js/live.js?v=25" defer></script>
<script src="assets/js/desk-dnd.js?v=11" defer></script>
<script src="assets/js/map-markers.js?v=6" defer></script>
<script src="assets/js/desk-compact-v2.js?v=13" defer></script>
<script src="assets/js/theme.js"></script>
</head>
<body>
<div class="app wide">
<header class="header">
  <div class="logo"><span>◆</span> Логистика</div>
  <div class="toolbar">
    <form method="get" style="display:flex;gap:8px;align-items:center">
      <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
    </form>
    <?php if (!class_exists('Auth') || Auth::isAdmin() || !empty($_SESSION['config_admin'])): ?>
    <button type="button" class="btn btn-ghost" id="rebuildBtn">Пересобрать</button>
    <?php if ($yandexKey !== '' && !empty($needGeo)): ?>
    <button type="button" class="btn btn-primary" id="geocodeBtn">Геокод (<?= count($needGeo) ?>)</button>
    <?php endif; ?>
    <a class="btn btn-ghost" href="admin/">Админка</a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="logout.php">Выйти</a>
  </div>
</header>
<div class="grid">
<section class="panel">
  <h2>Зоны <?= h($date) ?></h2>
  <?php foreach ($zones as $z):
    $st = $stats[$z['id']] ?? ['cnt'=>0,'weight'=>0];
    $cnt=(int)$st['cnt']; $w=(float)$st['weight'];
    $zcolor=!empty($z['poly_color'])?$z['poly_color']:'#1a73e8';
    $zv=$vehByZone[$z['id']]??[]; $totCap=0;
    foreach($zv as $vv) $totCap+=(float)$vv['capacity_kg'];
    $nVeh=count($zv); $noVeh=($nVeh===0 && $w>0.01);
    $zCovered = in_array((int)$z['id'], $coveredZoneIds, true);
    $zpct=$noVeh?100:($totCap>0?min(100,round($w/$totCap*100)):0);
    $barCls=$noVeh?'no-vehicle':($w>$totCap+0.01?'over':'');
    $wShow = number_format($w,0,'.','');
    $capShow = number_format($totCap,0,'.','');
  ?>
  <div class="zone-card" data-zone-drop="<?=(int)$z['id']?>" data-order-w="<?=$w?>" data-zone-compact="1" style="border-left:6px solid <?=h($zcolor)?>">
    <div class="name"><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:<?=h($zcolor)?>;margin-right:6px;vertical-align:middle"></span><?=h($z['name'])?></div>
    <div class="meta" style="display:none"><?=$cnt?> заявок · <?=$wShow?> кг</div>
    <span class="badge badge-corner <?=$noVeh?'badge-warn':'badge-ok'?>"><?=$cnt===0?($zCovered?'Везём':'Пусто'):($noVeh?'Нет машин':'В работе')?></span>
    <div class="bar <?=$barCls?>"><i style="width:<?=$zpct?>%"></i></div>
    <div class="zone-cap">
      <span class="cap-load" title="Загрузка, кг"><span class="ic ic-scale" aria-hidden="true"></span><?=$wShow?> / <?=$capShow?></span>
      <span class="cap-veh" title="Машин"><span class="ic ic-truck" aria-hidden="true"></span><?=$nVeh?></span>
      <span class="cap-orders meta-orders" title="Заявок"><span class="ic ic-box" aria-hidden="true"></span><?=$cnt?></span>
    </div>
    <div class="zone-vehicles">
      <?php foreach($zv as $vv): $vEmpty = !empty($vv['empty']); $vMerged = !empty($vv['merged']); ?>
      <div class="veh-chip<?=$vEmpty?' chip-empty':''?><?=$vMerged?' chip-merged':''?>" data-vehicle-id="<?=(int)$vv['vehicle_id']?>" data-zone-id="<?=(int)$z['id']?>"<?php if(!$vEmpty): ?> data-cap="<?=(float)$vv['capacity_kg']?>"<?php endif; ?> title="<?=$vMerged ? 'Объединённый рейс: машина везёт несколько районов' : ($vEmpty ? 'Пустой рейс — машина закреплена за зоной, заявок пока нет' : 'Перетащите в другую зону')?>">
        <span class="veh-name"><?=h($vv['name'])?></span><?php if(!empty($vv['plate'])): ?> <span class="veh-plate"><?=h($vv['plate'])?></span><?php endif; ?>
        <?php if($vMerged): ?><span class="veh-tag">объед.</span><?php endif; ?>
        <span class="veh-cap"><?=number_format((float)$vv['capacity_kg'],0,'.','')?></span>
      </div>
      <?php endforeach; ?>
      <?php if(!$zv): ?><span class="muted zone-empty">нет машин</span><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</section>
<section class="panel map-panel">
  <h2>Карта</h2>
  <?php if($yandexKey===''): ?>
  <p class="muted">Укажите ключ Яндекс.Карт в <a href="admin/?tab=settings">Настройках API</a> или config.php</p>
  <?php else: ?>
  <div id="map" class="map-box"></div>
  <?php endif; ?>
  <h2 style="margin-top:16px">Нераспределённые</h2>
  <div id="unassignedZone" style="min-height:72px;border:2px dashed var(--border);border-radius:10px;padding:8px">
    <?php if(!$freeOrders): ?>
    <p class="muted" style="text-align:center">Пусто</p>
    <?php else: foreach($freeOrders as $o): ?>
    <div class="drag-order" data-order-id="<?=(int)$o['id']?>" data-from-trip="" data-weight="<?=(float)$o['weight_kg']?>">
      <div class="ord-body">
        <div class="ord-num"><?=h($o['number']?:$o['external_id'])?></div>
        <div class="ord-addr"><?=h($o['address'])?></div>
        <?php if (!empty($o['partner'])): ?>
        <div class="ord-partner"><?=h($o['partner'])?></div>
        <?php endif; ?>
      </div>
      <span class="ord-kg"><?=number_format((float)$o['weight_kg'],0,'.','')?> кг</span>
    </div>
    <?php endforeach; endif; ?>
  </div>
</section>
<section class="panel">
  <h2>Рейсы</h2>
  <?php if(!$trips): ?><p class="muted">Нет рейсов</p><?php endif; ?>
  <?php foreach($trips as $t):
    $list=$itemsByTrip[$t['id']]??[]; $sum=0;
    foreach($list as $o) $sum+=(float)$o['weight_kg'];
    $cap=(float)$t['capacity_kg']; $pct=$cap>0?min(100,round($sum/$cap*100)):0; $over=$sum>$cap+0.01;
    $nOrd=count($list);
    $sumShow=number_format($sum,0,'.','');
    $capShow=number_format($cap,0,'.','');
    $canMerge = (($deskFilter['mode'] ?? 'all') === 'all') && $t['status'] !== 'done';
    if ($canMerge) {
      $mergedZoneIds = [(int)($t['zone_id'] ?? 0)];
      foreach ($list as $o) { if (!empty($o['zone_id'])) $mergedZoneIds[] = (int)$o['zone_id']; }
      $mergedZoneIds = array_values(array_unique($mergedZoneIds));
    }
  ?>
  <div class="trip" data-trip-id="<?=(int)$t['id']?>" data-cap="<?=$cap?>">
    <div class="title">
      <span class="trip-name"><?=h($t['vehicle_name'])?><?=$t['plate']?' · '.h($t['plate']):''?></span>
      <div class="trip-actions">
        <?php if ($canMerge): ?><button type="button" class="trip-add-zone" title="Добавить район на сегодня">+</button><?php endif; ?>
        <button type="button" class="trip-toggle">▾</button>
      </div>
    </div>
    <?php if ($canMerge): ?>
    <div class="zone-picker" hidden>
      <?php foreach ($zones as $pz): if ((int)$pz['id'] === (int)($t['zone_id'] ?? 0)) continue; $inTrip = in_array((int)$pz['id'], $mergedZoneIds, true); ?>
      <button type="button" data-zone-id="<?=(int)$pz['id']?>" data-in-trip="<?=$inTrip?1:0?>"><?=$inTrip?'✓ ':'+ '?><?=h($pz['name'])?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="muted"><?=h($t['zone_name']?:'Зона не указана')?><?php if(!empty($t['note'])): ?> · <span style="color:#e8710a"><?=h($t['note'])?></span><?php endif; ?> · <?=h($tripStatusLabels[$t['status']]??$t['status'])?></div>
    <div class="bar <?=$over?'over':''?>"><i style="width:<?=$pct?>%"></i></div>
    <div class="trip-weight muted" data-compact="1" data-compact-n="<?=$nOrd?>">
      <span class="trip-load" title="Загрузка, кг"><span class="ic ic-scale" aria-hidden="true"></span><?=$sumShow?> / <?=$capShow?></span>
      <span class="trip-orders meta-orders" title="Заявок"><span class="ic ic-box" aria-hidden="true"></span><?=$nOrd?></span>
    </div>
    <div class="trip-body">
      <div class="orders-list" style="margin-top:8px">
        <?php foreach($list as $o): $isNewRoute = ($o['tpl_pos'] === null); ?>
        <div class="drag-order<?=$isNewRoute?' route-new':''?>" data-order-id="<?=(int)$o['id']?>" data-from-trip="<?=(int)$t['id']?>" data-weight="<?=(float)$o['weight_kg']?>"<?=$isNewRoute?' title="Клиента ещё нет в сохранённом порядке маршрута — перетащите в нужное место"':''?>>
          <div class="ord-body">
            <div class="ord-num"><?=h($o['number']?:$o['external_id'])?><?php if($isNewRoute): ?> <span class="ord-new-badge">новый</span><?php endif; ?></div>
            <div class="ord-addr"><?=h($o['address'])?></div>
            <?php if (!empty($o['partner'])): ?>
            <div class="ord-partner"><?=h($o['partner'])?></div>
            <?php endif; ?>
          </div>
          <span class="ord-kg"><?=number_format((float)$o['weight_kg'],0,'.','')?> кг</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</section>
</div>
</div>
<script>
const mapPoints = <?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE) ?>;
const needGeo = <?= json_encode($needGeo, JSON_UNESCAPED_UNICODE) ?>;
const zonePolys = <?= json_encode(array_map(function($p){
  return ['zone_id'=>(int)$p['zone_id'],'zone_name'=>$p['zone_name']??'','color'=>(string)($p['color']??''),'points'=>json_decode((string)$p['polygon'],true)?:[]];
}, $zonePolys), JSON_UNESCAPED_UNICODE) ?>;

document.querySelectorAll('.trip-toggle').forEach(function(btn){
  btn.addEventListener('click', function(){ btn.closest('.trip').classList.toggle('collapsed'); });
});

// «+ район» в шапке рейса: добавить/убрать район на сегодня
document.querySelectorAll('.trip-add-zone').forEach(function(btn){
  btn.addEventListener('click', function(e){
    e.stopPropagation();
    var picker = btn.closest('.trip').querySelector('.zone-picker');
    document.querySelectorAll('.zone-picker').forEach(function(p){ if (p !== picker) p.hidden = true; });
    if (picker) picker.hidden = !picker.hidden;
  });
});
document.querySelectorAll('.zone-picker button').forEach(function(b){
  b.addEventListener('click', async function(e){
    e.stopPropagation();
    var trip = b.closest('.trip');
    b.disabled = true;
    try {
      var r = await fetch('api/trip_zone.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: b.getAttribute('data-in-trip') === '1' ? 'remove' : 'add',
          trip_id: parseInt(trip.getAttribute('data-trip-id'), 10),
          zone_id: parseInt(b.getAttribute('data-zone-id'), 10)
        })
      });
      var d = await r.json();
      if (!d.ok) throw new Error(d.error || 'Ошибка');
      if (window.deskAckLocalChange) window.deskAckLocalChange();
      location.reload();
    } catch (err) {
      alert(err.message || String(err));
      b.disabled = false;
    }
  });
});
document.addEventListener('click', function(e){
  if (!e.target.closest('.zone-picker') && !e.target.closest('.trip-add-zone')) {
    document.querySelectorAll('.zone-picker').forEach(function(p){ p.hidden = true; });
  }
});
var rb=document.getElementById('rebuildBtn');
if(rb) rb.addEventListener('click', async function(){
  rb.disabled=true; rb.textContent='…';
  try{ await fetch('api/rebuild.php?date=<?=urlencode($date)?>',{method:'POST'}); location.reload(); }
  catch(e){ alert(e.message); rb.disabled=false; rb.textContent='Пересобрать'; }
});

function drawZones(map) {
  if (!zonePolys || !zonePolys.length) return;
  zonePolys.forEach(function (z) {
    if (!z.points || z.points.length < 3) return;
    try {
      var poly = new ymaps.Polygon([z.points], { hintContent: z.zone_name || '' }, {
        fillColor: (z.color || '#1a73e8') + '33',
        strokeColor: z.color || '#1a73e8',
        strokeWidth: 2
      });
      map.geoObjects.add(poly);
    } catch (e) {}
  });
}
if (typeof ymaps !== 'undefined' && document.getElementById('map')) {
  ymaps.ready(function () {
    var map = new ymaps.Map('map', {
      center: [47.411, 40.091],
      zoom: 10,
      controls: ['zoomControl', 'typeSelector']
    });
    window.__logisticsMap = map;
    drawZones(map);
    setTimeout(function () {
      if (typeof window.rebuildMapMarks === 'function') {
        try { window.rebuildMapMarks(); } catch (e) {}
      }
    }, 100);
  });
}
</script>
</body>
</html>
