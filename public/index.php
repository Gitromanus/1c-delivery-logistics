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
<div class="app">
  <header class="header">
    <div class="logo"><span>◆</span> Логистика доставки</div>
    <div class="toolbar">
      <form method="get" style="display:flex;gap:8px;align-items:center">
        <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
      </form>
      <button type="button" class="btn btn-ghost" id="rebuildBtn">Пересобрать рейсы</button>
      <button type="button" class="btn btn-ghost" id="rezoneBtn" title="Временный инструмент">Пересчитать зоны</button>
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
        <div class="zone-card" style="border-left:6px solid <?= h($zcolor) ?>">
          <div class="name"><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:<?= h($zcolor) ?>;margin-right:6px;vertical-align:middle"></span><?= h($z['name']) ?></div>
          <div class="meta"><?= $cnt ?> заявок · <?= number_format($w, 0, '.', ' ') ?> кг</div>
          <?php if ($cnt === 0): ?>
            <span class="badge badge-ok">Пусто</span>
          <?php else: ?>
            <span class="badge badge-ok">В работе</span>
          <?php endif; ?>
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
      <?php if (!$freeOrders): ?>
        <p class="muted">Нет заявок со статусом «new».</p>
      <?php else: ?>
        <table>
          <thead><tr><th>№</th><th>Адрес</th><th>Кг</th></tr></thead>
          <tbody>
          <?php foreach ($freeOrders as $o): ?>
            <tr>
              <td><?= h($o['number'] ?: $o['external_id']) ?></td>
              <td><?= h(mb_strimwidth($o['address'], 0, 48, '…', 'UTF-8')) ?></td>
              <td><?= number_format((float) $o['weight_kg'], 0, '.', ' ') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
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
        <div class="trip">
          <div class="title"><?= h($t['vehicle_name']) ?><?= $t['plate'] ? ' · ' . h($t['plate']) : '' ?></div>
          <div class="muted"><?= h($t['zone_name'] ?: 'Зона не указана') ?> · <?= h($t['status']) ?></div>
          <div class="bar <?= $over ? 'over' : '' ?>"><i style="width:<?= $pct ?>%"></i></div>
          <div class="muted"><?= number_format($sum, 0, '.', ' ') ?> / <?= number_format($cap, 0, '.', ' ') ?> кг</div>
          <table style="margin-top:8px">
            <?php foreach ($list as $o): ?>
              <tr>
                <td><?= h($o['number'] ?: $o['external_id']) ?></td>
                <td><?= h(mb_strimwidth($o['address'], 0, 40, '…', 'UTF-8')) ?></td>
                <td><?= number_format((float) $o['weight_kg'], 0, '.', ' ') ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
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

document.getElementById('rezoneBtn').addEventListener('click', async () => {
  const btn = document.getElementById('rezoneBtn');
  btn.disabled = true;
  try {
    const r = await fetch('api/reassign_zones.php?date=<?= urlencode($date) ?>', { method: 'POST' });
    const data = await r.json();
    alert('Обработано: ' + data.processed + ', установлено зон: ' + data.updated + ', очищено: ' + data.cleared);
    location.reload();
  } catch (e) {
    alert(e.message);
    btn.disabled = false;
  }
});

function addMarks(map, points) {
  const withCoords = (points || []).filter(p => p.lat && p.lon);
  if (!withCoords.length) return null;
  const collection = new ymaps.GeoObjectCollection();
  withCoords.forEach(p => {
    const title = (p.number || p.external_id || '') + ' · ' + (p.weight_kg || 0) + ' кг';
    collection.add(new ymaps.Placemark([parseFloat(p.lat), parseFloat(p.lon)], {
      balloonContent: '<strong>' + title + '</strong><br>' + (p.partner || '') + '<br>' + (p.address || ''),
      iconCaption: p.number || p.external_id
    }, {
      preset: p.status === 'new' ? 'islands#orangeDotIcon' : 'islands#greenDotIcon'
    }));
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
          lines.push('Итого добавлено: ' + totalOk + ', не найдено: ' + totalFail + '.');
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
