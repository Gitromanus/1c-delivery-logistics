<?php /* compact desk restored */ ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Логистика доставки</title>
<link rel="stylesheet" href="assets/css/style.css?v=12">
<?php if ($yandexKey !== ''): ?>
<script src="https://api-maps.yandex.ru/2.1/?apikey=<?= h($yandexKey) ?>&lang=ru_RU"></script>
<?php endif; ?>
<script src="assets/js/live.js?v=17" defer></script>
<script src="assets/js/desk-dnd.js?v=7" defer></script>
<script src="assets/js/map-markers.js?v=6" defer></script>
<script src="assets/js/desk-compact-v2.js?v=12" defer></script>
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
    $zpct=$noVeh?100:($totCap>0?min(100,round($w/$totCap*100)):0);
    $barCls=$noVeh?'no-vehicle':($w>$totCap+0.01?'over':'');
  ?>
  <div class="zone-card" data-zone-drop="<?=(int)$z['id']?>" data-order-w="<?=$w?>" style="border-left:6px solid <?=h($zcolor)?>">
    <div class="name"><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:<?=h($zcolor)?>;margin-right:6px;vertical-align:middle"></span><?=h($z['name'])?></div>
    <div class="meta"><?=$cnt?> заявок · <?=number_format($w,0,'.',' ')?> кг</div>
    <span class="badge badge-corner <?=$noVeh?'badge-warn':'badge-ok'?>"><?=$cnt===0?'Пусто':($noVeh?'Нет машин':'В работе')?></span>
    <div class="bar <?=$barCls?>"><i style="width:<?=$zpct?>%"></i></div>
    <div class="zone-cap">Загружено: <?=number_format($w,0,'.',' ')?> / <?=number_format($totCap,0,'.',' ')?> кг · машин: <?=$nVeh?></div>
    <div class="zone-vehicles">
      <?php foreach($zv as $vv): ?>
      <div class="veh-chip" data-vehicle-id="<?=(int)$vv['vehicle_id']?>" data-zone-id="<?=(int)$z['id']?>" data-cap="<?=(float)$vv['capacity_kg']?>">
        <span class="veh-name"><?=h($vv['name'])?></span>
        <span class="veh-cap"><?=number_format((float)$vv['capacity_kg'],0,'.',' ')?> кг</span>
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
    <div class="drag-order" data-order-id="<?=(int)$o['id']?>" data-from-trip="" data-weight="<?=(float)$o['weight_kg']?>"
      style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:6px;border:1px solid #2f3546;border-radius:8px;background:#1c2130;cursor:grab;box-shadow:0 1px 2px rgba(0,0,0,.25)">
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:14px;color:#f1f3f7;line-height:1.25"><?=h($o['number']?:$o['external_id'])?></div>
        <div style="font-size:12px;color:#9aa0a6;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=h($o['address'])?></div>
        <?php if (!empty($o['partner'])): ?>
        <div style="font-size:12px;color:#b6bcc6;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px"><?=h($o['partner'])?></div>
        <?php endif; ?>
      </div>
      <span style="flex:0 0 auto;font-size:12px;font-weight:700;background:#2b3245;color:#dfe3ea;border-radius:20px;padding:3px 10px"><?=number_format((float)$o['weight_kg'],0,'.',' ')?> кг</span>
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
  ?>
  <div class="trip" data-trip-id="<?=(int)$t['id']?>" data-cap="<?=$cap?>">
    <div class="title">
      <span class="trip-name"><?=h($t['vehicle_name'])?><?=$t['plate']?' · '.h($t['plate']):''?></span>
      <div class="trip-actions">
        <button type="button" class="trip-toggle">▾</button>
      </div>
    </div>
    <div class="muted"><?=h($t['zone_name']?:'Зона не указана')?> · <?=h($tripStatusLabels[$t['status']]??$t['status'])?></div>
    <div class="bar <?=$over?'over':''?>"><i style="width:<?=$pct?>%"></i></div>
    <div class="trip-weight muted"><?=number_format($sum,0,'.',' ')?> / <?=number_format($cap,0,'.',' ')?> кг</div>
    <div class="trip-body">
      <div class="orders-list" style="margin-top:8px">
        <?php foreach($list as $o): ?>
        <div class="drag-order" data-order-id="<?=(int)$o['id']?>" data-from-trip="<?=(int)$t['id']?>" data-weight="<?=(float)$o['weight_kg']?>"
          style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:6px;border:1px solid #2f3546;border-radius:8px;background:#1c2130;cursor:grab;box-shadow:0 1px 2px rgba(0,0,0,.25)">
          <div style="flex:1;min-width:0">
            <div style="font-weight:700;font-size:14px;color:#f1f3f7;line-height:1.25"><?=h($o['number']?:$o['external_id'])?></div>
            <div style="font-size:12px;color:#9aa0a6;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=h($o['address'])?></div>
            <?php if (!empty($o['partner'])): ?>
            <div style="font-size:12px;color:#b6bcc6;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px"><?=h($o['partner'])?></div>
            <?php endif; ?>
          </div>
          <span style="flex:0 0 auto;font-size:12px;font-weight:700;background:#2b3245;color:#dfe3ea;border-radius:20px;padding:3px 10px"><?=number_format((float)$o['weight_kg'],0,'.',' ')?> кг</span>
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
