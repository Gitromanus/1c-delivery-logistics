<?php
require __DIR__ . '/bootstrap.php';
$config = require (defined('APP_ROOT') ? APP_ROOT : __DIR__) . '/config.php';
$yandexKey = (string) ($config['yandex_maps_key'] ?? '');

$pdo = Database::pdo();
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$zones = $pdo->query('SELECT * FROM zones WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();

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
          ?>
        <div class="zone-card">
          <div class="name"><?= h($z['name']) ?></div>
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
<script>
const mapPoints = <?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE) ?>;
const needGeo = <?= json_encode($needGeo, JSON_UNESCAPED_UNICODE) ?>;

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

    const geoBtn = document.getElementById('geocodeBtn');
    if (geoBtn && needGeo.length) {
      geoBtn.addEventListener('click', async () => {
        geoBtn.disabled = true;
        geoBtn.textContent = 'Геокодинг…';
        try {
          const r = await fetch('api/geocode_front.php?date=<?= urlencode($date) ?>', { method: 'POST' });
          const text = await r.text();
          let data;
          try {
            data = JSON.parse(text);
          } catch (e) {
            alert('Сервер вернул не JSON (вероятно, защита хостинга «Подтвердите действие» или ошибка PHP). Повторите через несколько секунд.\n\nОтвет:\n' + text.slice(0, 160));
            geoBtn.disabled = false;
            geoBtn.textContent = 'Геокод с карты';
            return;
          }
          const done = data.geocoded || 0;
          const failed = data.failed || 0;
          let msg = done + ' добавлено, ' + failed + ' не найдено' + (failed ? '.' : '.');
          if (failed && data.sample_errors && data.sample_errors.length) {
            msg += '\nНе найдены:';
            data.sample_errors.forEach(function (e) {
              msg += '\n• ' + (e.address || '') + ' — ' + (e.error || '');
            });
          }
          alert(msg);
          location.reload();
        } catch (e) {
          alert(e.message);
          geoBtn.disabled = false;
          geoBtn.textContent = 'Геокод с карты';
        }
      });
    }
  });
}
<?php endif; ?>
</script>
</body>
</html>
