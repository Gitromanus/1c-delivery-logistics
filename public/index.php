<?php
require __DIR__ . '/bootstrap.php';
$config = require (defined('APP_ROOT') ? APP_ROOT : __DIR__) . '/config.php';
$yandexKey = (string) ($config['yandex_maps_key'] ?? '');

$pdo = Database::pdo();
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$zones = $pdo->query(
    "SELECT z.*, zp.color AS poly_color
     FROM zones z
     LEFT JOIN zone_polygons zp ON zp.zone_id = z.id
     WHERE z.is_active = 1
     ORDER BY z.sort_order, z.name"
)->fetchAll();

$statsStmt = $pdo->prepare(
    "SELECT zone_id,
            COUNT(*) AS cnt,
            COALESCE(SUM(weight_kg),0) AS weight
     FROM orders
     WHERE doc_date = ? AND status <> 'cancelled'
     GROUP BY zone_id"
);
$statsStmt->execute([$date]);
$stats = [];
foreach ($statsStmt->fetchAll() as $row) {
    $stats[$row['zone_id'] ?? 0] = $row;
}

$tripsStmt = $pdo->prepare(
    "SELECT t.*, v.name AS vehicle_name, v.capacity_kg, v.plate, z.name AS zone_name
     FROM trips t
     JOIN vehicles v ON v.id = t.vehicle_id
     LEFT JOIN zones z ON z.id = t.zone_id
     WHERE t.trip_date = ? AND t.status <> 'cancelled'
     ORDER BY t.id"
);
$tripsStmt->execute([$date]);
$trips = $tripsStmt->fetchAll();

$itemsByTrip = [];
if ($trips) {
    $ids = array_column($trips, 'id');
    $in = implode(',', array_map('intval', $ids));
    $items = $pdo->query(
        "SELECT ti.trip_id, o.*
         FROM trip_items ti
         JOIN orders o ON o.id = ti.order_id
         WHERE ti.trip_id IN ($in)
         ORDER BY ti.sort_order, o.id"
    )->fetchAll();
    foreach ($items as $it) {
        $itemsByTrip[$it['trip_id']][] = $it;
    }
}

// Машины по зонам (из привязок vehicle_zones)
$vehZoneRows = $pdo->query(
    "SELECT vz.zone_id, v.id AS vehicle_id, v.name, v.capacity_kg
     FROM vehicle_zones vz
     JOIN vehicles v ON v.id = vz.vehicle_id
     WHERE v.is_active = 1
     ORDER BY v.name"
)->fetchAll();
$vehByZone = [];
foreach ($vehZoneRows as $vr) {
    $vehByZone[(int) $vr['zone_id']][] = $vr;
}

$unassigned = $pdo->prepare(
    "SELECT * FROM orders WHERE doc_date = ? AND status = 'new' ORDER BY id DESC LIMIT 100"
);
$unassigned->execute([$date]);
$freeOrders = $unassigned->fetchAll();

$mapOrders = $pdo->prepare(
    "SELECT id, number, external_id, partner, address, weight_kg, lat, lon, status, zone_id
     FROM orders WHERE doc_date = ? AND status <> 'cancelled'"
);
$mapOrders->execute([$date]);
$mapPoints = $mapOrders->fetchAll();

// Полигоны зон для отрисовки на карте
$zonePolys = $pdo->query(
    "SELECT zp.zone_id, zp.polygon, zp.color, z.name AS zone_name
     FROM zone_polygons zp JOIN zones z ON z.id = zp.zone_id"
)->fetchAll();

$needGeo = array_values(array_filter($mapPoints, function ($p) {
    return empty($p['lat']) || empty($p['lon']);
}));

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Логистика доставки</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <?php if ($yandexKey !== ''): ?>
  <script src="https://api-maps.yandex.ru/2.1/?apikey=<?= h($yandexKey) ?>&lang=ru_RU"></script>
  <?php endif; ?>
</head>
<body>
<div class="app wide">
  <header class="header">
    <div class="logo"><span>◆</span> Логистика доставки</div>
    <div class="toolbar">
      <form method="get" style="display:flex;gap:8px;align-items:center">
        <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
      </form>
      <button type="button" class="btn btn-ghost" id="rebuildBtn">Пересобрать рейсы</button>
      <?php if ($yandexKey !== '' && count($needGeo) > 0): ?>
      <button type="button" class="btn btn-primary" id="geocodeBtn">Геокод с карты (<?= count($needGeo) ?>)</button>
      <?php endif; ?>
      <a class="btn btn-ghost" href="admin/">Админка</a>
    </div>
  </header>

  <div class="grid">
    <section class="panel">
      <h2>Зоны на <?= h($date) ?></h2>
      <?php foreach ($zones as $z):
          $st = $stats[$z['id']] ?? ['cnt' => 0, 'weight' => 0];
          $cnt = (int) $st['cnt'];
          $w = (float) $st['weight'];
          $zcolor = !empty($z['poly_color']) ? $z['poly_color'] : '#1a73e8';
          ?>
        <?php
          $zv = $vehByZone[$z['id']] ?? [];
          $totCap = 0;
          foreach ($zv as $vv) { $totCap += (float) $vv['capacity_kg']; }
        ?>
        <div class="zone-card" data-zone-drop="<?= (int) $z['id'] ?>" data-order-w="<?= $w ?>" style="border-left:6px solid <?= h($zcolor) ?>">
          <div class="name"><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:<?= h($zcolor) ?>;margin-right:6px;vertical-align:middle"></span><?= h($z['name']) ?></div>
          <div class="meta"><?= $cnt ?> заявок · <?= number_format($w, 0, '.', ' ') ?> кг</div>
          <?php if ($cnt === 0): ?>
            <span class="badge badge-ok">Пусто</span>
          <?php else: ?>
            <span class="badge badge-ok">В работе</span>
          <?php endif; ?>
          <?php $zpct = $totCap > 0 ? min(100, round($w / $totCap * 100)) : 0; ?>
          <div class="bar <?= $w > $totCap + 0.01 ? 'over' : '' ?>"><i style="width:<?= $zpct ?>%"></i></div>
          <div class="zone-cap">Загружено: <?= number_format($w, 0, '.', ' ') ?> / <?= number_format($totCap, 0, '.', ' ') ?> кг · машин: <?= count($zv) ?></div>
          <div class="zone-vehicles">
            <?php foreach ($zv as $vv): ?>
              <div class="veh-chip" data-vehicle-id="<?= (int) $vv['vehicle_id'] ?>" data-zone-id="<?= (int) $z['id'] ?>" data-cap="<?= (float) $vv['capacity_kg'] ?>" title="Перетащите в другую зону">
                <span class="veh-name"><?= h($vv['name']) ?></span>
                <span class="veh-cap"><?= number_format((float) $vv['capacity_kg'], 0, '.', ' ') ?> кг</span>
              </div>
            <?php endforeach; ?>
            <?php if (!$zv): ?>
              <span class="muted zone-empty">нет машин</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="panel map-panel">
      <h2>Карта заявок</h2>
      <?php if ($yandexKey === ''): ?>
        <p class="muted">Укажите <code>yandex_maps_key</code> в config.php</p>
      <?php else: ?>
        <div id="map" class="map-box"></div>
        <p class="muted" style="margin-top:8px" id="mapHint">
          <?php if (count($needGeo) > 0): ?>
            Без координат: <?= count($needGeo) ?>. Нажмите «Геокод с карты» — через JS API Яндекса.
          <?php else: ?>
            Метки по сохранённым координатам.
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <h2 style="margin-top:16px">Нераспределённые</h2>
      <div id="unassignedZone" style="min-height:72px;border:2px dashed #2f3546;border-radius:10px;padding:8px;background:#14161f;">
        <?php if (!$freeOrders): ?>
          <p class="muted" style="text-align:center;padding:8px 0">Пусто — перетащите заявку сюда из рейса</p>
        <?php else: ?>
          <?php foreach ($freeOrders as $o): ?>
            <div class="drag-order" data-order-id="<?= (int) $o['id'] ?>" data-from-trip="" data-weight="<?= (float) $o['weight_kg'] ?>"
                 style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #2f3546;border-radius:8px;margin-bottom:6px;background:#1c2130;cursor:grab;box-shadow:0 1px 2px rgba(0,0,0,.25)">
              <div style="min-width:0;flex:1">
                <div style="font-weight:700;font-size:14px;color:#f1f3f7;line-height:1.25"><?= h($o['number'] ?: $o['external_id']) ?></div>
                <div style="font-size:12px;color:#9aa0a6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($o['address']) ?></div>
                <?php if (!empty($o['partner'])): ?>
                <div style="font-size:12px;color:#b6bcc6;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px"><?= h($o['partner']) ?></div>
                <?php endif; ?>
              </div>
              <span style="flex:0 0 auto;font-size:12px;font-weight:700;background:#2b3245;color:#dfe3ea;border-radius:20px;padding:3px 10px"><?= number_format((float) $o['weight_kg'], 0, '.', ' ') ?> кг</span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel">
      <h2>Рейсы</h2>
      <?php if (!$trips): ?>
        <p class="muted">Рейсов нет. Нажмите «Пересобрать рейсы».</p>
      <?php endif; ?>
      <?php foreach ($trips as $t):
          $list = $itemsByTrip[$t['id']] ?? [];
          $sum = 0;
          foreach ($list as $o) { $sum += (float) $o['weight_kg']; }
          $cap = (float) $t['capacity_kg'];
          $pct = $cap > 0 ? min(100, round($sum / $cap * 100)) : 0;
          $over = $sum > $cap + 0.01;
          ?>
        <div class="trip" data-trip-id="<?= (int) $t['id'] ?>" data-cap="<?= $cap ?>">
          <div class="title">
            <span><?= h($t['vehicle_name']) ?><?= (!empty($t['plate']) && stripos((string) $t['vehicle_name'], (string) $t['plate']) === false) ? ' · ' . h($t['plate']) : '' ?></span>
            <button type="button" class="trip-toggle" aria-label="Свернуть/развернуть" title="Свернуть/развернуть">▾</button>
          </div>
          <div class="muted"><?= h($t['zone_name'] ?: 'Зона не указана') ?> · <?= h($t['status']) ?></div>
          <div class="bar <?= $over ? 'over' : '' ?>"><i style="width:<?= $pct ?>%"></i></div>
          <div class="muted trip-weight"><?= number_format($sum, 0, '.', ' ') ?> / <?= number_format($cap, 0, '.', ' ') ?> кг</div>
          <div class="trip-body">
            <div class="orders-list" style="margin-top:8px">
              <?php foreach ($list as $o): ?>
                <div class="drag-order" data-order-id="<?= (int) $o['id'] ?>" data-from-trip="<?= (int) $t['id'] ?>" data-weight="<?= (float) $o['weight_kg'] ?>"
                     style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #2f3546;border-radius:8px;margin-bottom:6px;background:#1c2130;cursor:grab;box-shadow:0 1px 2px rgba(0,0,0,.25)">
                  <div style="min-width:0;flex:1">
                    <div style="font-weight:700;font-size:14px;color:#f1f3f7;line-height:1.25"><?= h($o['number'] ?: $o['external_id']) ?></div>
                    <div style="font-size:12px;color:#9aa0a6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($o['address']) ?></div>
                    <?php if (!empty($o['partner'])): ?>
                    <div style="font-size:12px;color:#b6bcc6;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px"><?= h($o['partner']) ?></div>
                    <?php endif; ?>
                  </div>
                  <span style="flex:0 0 auto;font-size:12px;font-weight:700;background:#2b3245;color:#dfe3ea;border-radius:20px;padding:3px 10px"><?= number_format((float) $o['weight_kg'], 0, '.', ' ') ?> кг</span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  </div>
  <footer class="footer">УТ 11 · Agent+</footer>
</div>

<!-- Окно результатов геокодирования (текст можно скопировать) -->
<div id="geoLog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;color:#111;max-width:760px;width:100%;max-height:82vh;display:flex;flex-direction:column;border-radius:8px;padding:16px;box-sizing:border-box;box-shadow:0 10px 40px rgba(0,0,0,.4);">
    <div style="margin-bottom:8px;font-weight:bold;font-size:14px;">Результат геокодирования</div>
    <textarea id="geoLogText" readonly style="flex:1;width:100%;min-height:200px;font-family:monospace;font-size:12px;resize:vertical;box-sizing:border-box;"></textarea>
    <div style="margin-top:10px;text-align:right;">
      <button type="button" class="btn btn-primary" id="geoLogCopy">Скопировать</button>
      <button type="button" class="btn btn-ghost" id="geoLogClose">Закрыть и обновить</button>
    </div>
  </div>
</div>

<script>
const mapPoints = <?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE) ?>;
const needGeo = <?= json_encode($needGeo, JSON_UNESCAPED_UNICODE) ?>;
const zonePolys = <?= json_encode(array_map(function ($p) {
    return [
        'zone_id' => (int) $p['zone_id'],
        'zone_name' => $p['zone_name'],
        'color' => (string) ($p['color'] ?? ''),
        'points' => json_decode((string) $p['polygon'], true) ?: [],
    ];
}, $zonePolys), JSON_UNESCAPED_UNICODE) ?>;

// Окно результатов геокодирования (можно скопировать)
function showGeoLog(lines) {
  const ta = document.getElementById('geoLogText');
  if (ta) ta.value = lines.join('\n');
  const box = document.getElementById('geoLog');
  if (box) box.style.display = 'flex';
}
const logClose = document.getElementById('geoLogClose');
const logCopy = document.getElementById('geoLogCopy');
if (logClose) logClose.addEventListener('click', () => location.reload());
if (logCopy) logCopy.addEventListener('click', () => {
  const ta = document.getElementById('geoLogText');
  if (!ta) return;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(ta.value).then(() => { logCopy.textContent = 'Скопировано'; });
  } else {
    ta.focus(); ta.select();
    try { document.execCommand('copy'); logCopy.textContent = 'Скопировано'; } catch (e) {}
  }
});

document.getElementById('rebuildBtn').addEventListener('click', async () => {
  const btn = document.getElementById('rebuildBtn');
  btn.disabled = true;
  btn.textContent = 'Сборка…';
  try {
    const r = await fetch('api/rebuild.php?date=<?= urlencode($date) ?>', { method: 'POST' });
    const data = await r.json();
    if (data.warnings && data.warnings.length) alert('Готово.\n\n' + data.warnings.join('\n'));
    location.reload();
  } catch (e) {
    alert(e.message);
    btn.disabled = false;
    btn.textContent = 'Пересобрать рейсы';
  }
});

// Кастомное перетаскивание мышью: непрозрачная карточка + раздвигание списка (место вставки)
let ddPotential = null;  // кандидат до срабатывания порога
let ddDrag = null;       // активное перетаскивание
let ddGap = null;        // индикатор места вставки

function ddGhost(src) {
  const g = src.cloneNode(true);
  const w = src.getBoundingClientRect().width;
  g.classList.add('dd-ghost');
  g.style.position = 'fixed';
  g.style.left = '-9999px';
  g.style.top = '0';
  g.style.width = w + 'px';
  g.style.pointerEvents = 'none';
  document.body.appendChild(g);
  return g;
}
function ddMoveGhost(e) {
  if (!ddDrag.ghost) return;
  const w = ddDrag.ghost.offsetWidth;
  ddDrag.ghost.style.left = (e.clientX - w / 2) + 'px';
  ddDrag.ghost.style.top = (e.clientY - 14) + 'px';
}
function ddClearGap() {
  if (ddGap && ddGap.parentNode) ddGap.parentNode.removeChild(ddGap);
  ddGap = null;
}
function ddShowGap(container, beforeEl) {
  ddClearGap();
  ddGap = document.createElement('div');
  ddGap.className = 'dd-gap';
  if (beforeEl) container.insertBefore(ddGap, beforeEl);
  else container.appendChild(ddGap);
}
function ddItems(container) {
  return Array.from(container.querySelectorAll(':scope > .drag-order, :scope > .veh-chip'))
    .filter(function (it) { return !it.classList.contains('dd-source'); });
}
function ddInsertBefore(items, y) {
  for (var i = 0; i < items.length; i++) {
    var r = items[i].getBoundingClientRect();
    if (y < r.top + r.height / 2) return items[i];
  }
  return null;
}
function ddDropTarget(e) {
  const under = document.elementFromPoint(e.clientX, e.clientY);
  if (!under) return null;
  if (ddDrag.isOrder) {
    const trip = under.closest('.trip');
    if (trip) {
      const container = trip.querySelector('.orders-list');
      return { kind: 'trip', tripId: parseInt(trip.getAttribute('data-trip-id'), 10), container: container || trip };
    }
    const un = under.closest('#unassignedZone');
    if (un) return { kind: 'un', container: un };
    return null;
  }
  const zone = under.closest('[data-zone-drop]');
  if (zone) {
    const container = zone.querySelector('.zone-vehicles');
    return { kind: 'zone', zoneId: parseInt(zone.getAttribute('data-zone-drop'), 10), container: container || zone };
  }
  return null;
}
function ddStart(el, isOrder, e) {
  ddDrag = {
    isOrder: isOrder,
    el: el,
    info: isOrder
      ? { order_id: parseInt(el.getAttribute('data-order-id'), 10), from_trip: el.getAttribute('data-from-trip') || null }
      : { vehicle_id: parseInt(el.getAttribute('data-vehicle-id'), 10), from_zone: parseInt(el.getAttribute('data-zone-id'), 10) },
    ghost: ddGhost(el),
    target: null
  };
  el.classList.add('dd-source');
  document.body.classList.add('dd-dragging');
  ddMoveGhost(e);
}
function fmtKg(v) {
  v = Math.round(v || 0);
  return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}
function refreshTrip(tripEl) {
  if (!tripEl) return;
  const cap = parseFloat(tripEl.getAttribute('data-cap')) || 0;
  let sum = 0;
  tripEl.querySelectorAll('.orders-list > .drag-order').forEach(function (o) {
    sum += parseFloat(o.getAttribute('data-weight')) || 0;
  });
  const pct = cap > 0 ? Math.min(100, Math.round(sum / cap * 100)) : 0;
  const over = sum > cap + 0.01;
  const bar = tripEl.querySelector('.bar');
  if (bar) {
    bar.classList.toggle('over', over);
    const i = bar.querySelector('i');
    if (i) i.style.width = pct + '%';
  }
  const wl = tripEl.querySelector('.trip-weight');
  if (wl) wl.textContent = fmtKg(sum) + ' / ' + fmtKg(cap) + ' кг';
}
function refreshZone(zoneEl) {
  if (!zoneEl) return;
  let cap = 0, n = 0;
  zoneEl.querySelectorAll('.zone-vehicles > .veh-chip').forEach(function (ch) {
    cap += parseFloat(ch.getAttribute('data-cap')) || 0;
    n++;
  });
  const orderW = parseFloat(zoneEl.getAttribute('data-order-w')) || 0;
  const pct = cap > 0 ? Math.min(100, Math.round(orderW / cap * 100)) : 0;
  const bar = zoneEl.querySelector('.bar');
  if (bar) {
    bar.classList.toggle('over', orderW > cap + 0.01);
    const i = bar.querySelector('i');
    if (i) i.style.width = pct + '%';
  }
  const capEl = zoneEl.querySelector('.zone-cap');
  if (capEl) capEl.textContent = 'Загружено: ' + fmtKg(orderW) + ' / ' + fmtKg(cap) + ' кг · машин: ' + n;
}
function ddApplyMove(drag, target, insertBeforeEl) {
  const el = drag.el;
  const tContainer = target.container;
  if (tContainer) {
    if (insertBeforeEl && insertBeforeEl.parentNode === tContainer) tContainer.insertBefore(el, insertBeforeEl);
    else tContainer.appendChild(el);
  }
  if (drag.isOrder) {
    const oldTrip = drag.info.from_trip ? parseInt(drag.info.from_trip, 10) : null;
    if (target.kind === 'trip') {
      el.setAttribute('data-from-trip', target.tripId);
      if (oldTrip) refreshTrip(document.querySelector('.trip[data-trip-id="' + oldTrip + '"]'));
      refreshTrip(document.querySelector('.trip[data-trip-id="' + target.tripId + '"]'));
    } else if (target.kind === 'un') {
      el.setAttribute('data-from-trip', '');
      if (oldTrip) refreshTrip(document.querySelector('.trip[data-trip-id="' + oldTrip + '"]'));
    }
  } else {
    const oldZone = drag.info.from_zone ? parseInt(drag.info.from_zone, 10) : null;
    el.setAttribute('data-zone-id', target.zoneId);
    if (oldZone) refreshZone(document.querySelector('[data-zone-drop="' + oldZone + '"]'));
    refreshZone(document.querySelector('[data-zone-drop="' + target.zoneId + '"]'));
  }
}
async function ddFinish(e) {
  const drag = ddDrag;
  ddDrag = null;
  ddPotential = null;
  document.body.classList.remove('dd-dragging');
  if (drag.el) drag.el.classList.remove('dd-source');

  let target = drag.target;
  if (!target) target = ddDropTarget(e);
  // запоминаем место вставки ДО очистки индикатора
  const tContainer = target ? target.container : null;
  const insertBeforeEl = (ddGap && ddGap.parentNode === tContainer) ? ddGap.nextElementSibling : null;

  if (drag.ghost && drag.ghost.parentNode) drag.ghost.parentNode.removeChild(drag.ghost);
  ddClearGap();

  if (!target) return;
  const info = drag.info;
  try {
    let params, url;
    if (drag.isOrder) {
      if (target.kind === 'trip') {
        const toTrip = target.tripId;
        if (info.from_trip && parseInt(info.from_trip, 10) === toTrip) {
          params = { action: 'reorder', trip_id: toTrip, order_ids: ddBuildOrderList(target.container, info.order_id, e.clientY) };
        } else {
          params = { action: info.from_trip ? 'move' : 'add', order_id: info.order_id, from_trip_id: info.from_trip, to_trip_id: toTrip };
        }
        url = 'api/trip_order.php';
      } else if (target.kind === 'un') {
        if (!info.from_trip) return;
        params = { action: 'remove', order_id: info.order_id, trip_id: parseInt(info.from_trip, 10) };
        url = 'api/trip_order.php';
      } else {
        return;
      }
    } else {
      if (target.kind !== 'zone') return;
      if (parseInt(info.from_zone, 10) === target.zoneId) return;
      params = { action: 'move', vehicle_id: info.vehicle_id, zone_id: target.zoneId };
      url = 'api/vehicle_zone.php';
    }
    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(params) });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'error');
    ddApplyMove(drag, target, insertBeforeEl);
  } catch (err) { alert(err.message); }
}
function ddBuildOrderList(container, orderId, y) {
  const all = Array.from(container.querySelectorAll(':scope > .drag-order'));
  const ids = all.map(function (el) { return parseInt(el.getAttribute('data-order-id'), 10); });
  const fi = ids.indexOf(orderId);
  if (fi !== -1) ids.splice(fi, 1);
  const rest = all.filter(function (el) { return parseInt(el.getAttribute('data-order-id'), 10) !== orderId; });
  let ins = ids.length;
  for (let i = 0; i < rest.length; i++) {
    const rr = rest[i].getBoundingClientRect();
    if (y < rr.top + rr.height / 2) { ins = i; break; }
    ins = i + 1;
  }
  ids.splice(ins, 0, orderId);
  return ids;
}

document.addEventListener('mousedown', function (e) {
  if (e.button !== 0) return;
  const ord = e.target.closest('.drag-order');
  const chip = e.target.closest('.veh-chip');
  if (!ord && !chip) return;
  ddPotential = { el: ord || chip, isOrder: !!ord, x: e.clientX, y: e.clientY };
});
document.addEventListener('mousemove', function (e) {
  if (ddPotential && !ddDrag) {
    if (Math.abs(e.clientX - ddPotential.x) + Math.abs(e.clientY - ddPotential.y) > 6) {
      ddStart(ddPotential.el, ddPotential.isOrder, e);
      ddPotential = null;
    }
  }
  if (!ddDrag) return;
  e.preventDefault();
  ddMoveGhost(e);
  const t = ddDropTarget(e);
  ddDrag.target = t;
  ddClearGap();
  if (t && t.container) {
    const items = ddItems(t.container);
    ddShowGap(t.container, ddInsertBefore(items, e.clientY));
  }
});
document.addEventListener('mouseup', function (e) {
  if (ddPotential) ddPotential = null;
  if (!ddDrag) return;
  ddFinish(e);
});

// Свернуть / развернуть рейс
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.trip-toggle');
  if (!btn) return;
  const trip = btn.closest('.trip');
  if (!trip) return;
  const collapsed = trip.classList.toggle('collapsed');
  btn.textContent = collapsed ? '▸' : '▾';
});

// Связка «карточка заказа ↔ точка на карте»
let orderMarks = {};
function highlightOrder(id) {
  document.querySelectorAll('.drag-order').forEach(function (c) { c.style.outline = ''; c.style.outlineOffset = ''; });
  Object.keys(orderMarks).forEach(function (k) {
    const m = orderMarks[k];
    if (m.options.get('preset') === 'islands#redCircleDotIcon') {
      m.options.set('preset', m.__origPreset || 'islands#greenDotIcon');
    }
  });
  if (id == null) return;
  const card = document.querySelector('.drag-order[data-order-id="' + id + '"]');
  if (card) { card.style.outline = '2px solid #f59e0b'; card.style.outlineOffset = '-2px'; }
  const m = orderMarks[id];
  if (m) {
    try { window.__logisticsMap.panTo(m.geometry.getCoordinates(), { checkZoomRange: true, delay: 0 }); } catch (e) {}
    m.options.set('preset', 'islands#redCircleDotIcon');
  }
}
document.querySelectorAll('.drag-order').forEach(function (c) {
  c.addEventListener('click', function () {
    highlightOrder(parseInt(c.getAttribute('data-order-id'), 10));
  });
});

function addMarks(map, points) {
  const withCoords = (points || []).filter(p => p.lat && p.lon);
  if (!withCoords.length) return null;
  const collection = new ymaps.GeoObjectCollection();
  withCoords.forEach(p => {
    const title = (p.number || p.external_id || '') + ' · ' + (p.weight_kg || 0) + ' кг';
    const preset = p.status === 'new' ? 'islands#orangeDotIcon' : 'islands#greenDotIcon';
    const mark = new ymaps.Placemark([parseFloat(p.lat), parseFloat(p.lon)], {
      balloonContent: '<strong>' + title + '</strong><br>' + (p.partner || '') + '<br>' + (p.address || ''),
      iconCaption: p.number || p.external_id
    }, { preset: preset });
    mark.__origPreset = preset;
    mark.events.add('click', function () { highlightOrder(p.id); });
    orderMarks[p.id] = mark;
    collection.add(mark);
  });
  map.geoObjects.add(collection);
  try {
    map.setBounds(collection.getBounds(), { checkZoomRange: true, zoomMargin: 40 });
  } catch (e) {}
  return collection;
}

function drawZones(map) {
  (zonePolys || []).forEach(function (zp) {
    if (!zp.points || !zp.points.length) return;
    const color = zp.color || '#1a73e8';
    const poly = new ymaps.Polygon([zp.points], { hintContent: zp.zone_name }, {
      fillColor: color,
      fillOpacity: 0.10,
      strokeColor: color,
      strokeWidth: 2,
      strokeOpacity: 0.75
    });
    map.geoObjects.add(poly);
  });
}

<?php if ($yandexKey !== ''): ?>
if (typeof ymaps !== 'undefined') {
  ymaps.ready(function () {
    const map = new ymaps.Map('map', {
      center: [47.411, 40.091],
      zoom: 10,
      controls: ['zoomControl', 'typeSelector']
    });
    window.__logisticsMap = map;
    addMarks(map, mapPoints);
    drawZones(map);

    const geoBtn = document.getElementById('geocodeBtn');
    if (geoBtn && needGeo.length) {
      geoBtn.addEventListener('click', async () => {
        geoBtn.disabled = true;
        geoBtn.textContent = 'Геокодинг…';
        const lines = [];
        const providers = {};
        const errs = [];
        let totalOk = 0;
        let totalFail = 0;
        try {
          for (let iter = 0; iter < 200; iter++) {
            const r = await fetch('api/geocode_front.php?date=<?= urlencode($date) ?>', { method: 'POST' });
            const text = await r.text();
            let data;
            try {
              data = JSON.parse(text);
            } catch (e) {
              showGeoLog([
                'Сервер вернул не JSON (возможно, защита хостинга «Подтвердите действие» или ошибка PHP).',
                'Повторите через несколько секунд.',
                '',
                'Ответ:',
                text.slice(0, 500),
              ]);
              geoBtn.disabled = false;
              geoBtn.textContent = 'Геокод с карты';
              return;
            }
            totalOk += data.geocoded || 0;
            totalFail += data.failed || 0;
            if (data.sample_errors && data.sample_errors.length) errs.push.apply(errs, data.sample_errors);
            if (data.providers) {
              for (var k in data.providers) providers[k] = (providers[k] || 0) + data.providers[k];
            }
            if (!data.left_batch || data.left_batch < 1) break;           // больше нечего обрабатывать
            if ((data.geocoded || 0) === 0 && (data.failed || 0) > 0) break; // дальше только «не находятся»
            geoBtn.textContent = 'Геокодинг… ' + (iter + 1) + '/200';
          }
          // Пересчёт зон по новым координатам после геокодинга
          let rzInfo = '';
          try {
            const rz = await fetch('api/reassign_zones.php?date=<?= urlencode($date) ?>', { method: 'POST' });
            const rzd = await rz.json();
            rzInfo = 'Зоны: обновлено ' + (rzd.updated || 0) + ', очищено ' + (rzd.cleared || 0);
          } catch (e) {}

          lines.push('Итого добавлено: ' + totalOk + ', не найдено: ' + totalFail + '.');
          if (rzInfo) lines.push(rzInfo);
          const provLines = Object.keys(providers).map(function (k) { return k + ': ' + providers[k]; }).join(', ');
          if (provLines) lines.push('Провайдеры: ' + provLines);
          if (errs.length) {
            lines.push('');
            lines.push('Не найдены:');
            errs.slice(0, 20).forEach(function (e) {
              lines.push((e.address || '') + '\t' + (e.error || ''));
            });
          }
          showGeoLog(lines);
        } catch (e) {
          showGeoLog([e.message]);
        }
        geoBtn.disabled = false;
        geoBtn.textContent = 'Геокод с карты';
      });
    }
  });
}
<?php endif; ?>
</script>
</body>
</html>
