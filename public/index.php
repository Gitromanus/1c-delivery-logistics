<?php
require __DIR__ . '/bootstrap.php';
$config = require (defined('APP_ROOT') ? APP_ROOT : __DIR__) . '/config.php';
$yandexKey = (string) ($config['yandex_maps_key'] ?? '');
$pdo = Database::pdo();
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
function h(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$orders = $pdo->prepare("SELECT * FROM orders WHERE doc_date = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 200");
$orders->execute([$date]);
$rows = $orders->fetchAll();
$free = array_values(array_filter($rows, function ($o) { return ($o['status'] ?? '') === 'new'; }));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Логистика доставки</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app">
  <header class="header">
    <div class="logo"><span>◆</span> Логистика доставки</div>
    <div class="toolbar">
      <form method="get"><input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()"></form>
      <button type="button" class="btn btn-ghost" id="rebuildBtn">Пересобрать рейсы</button>
      <a class="btn btn-ghost" href="admin/">Админка</a>
    </div>
  </header>
  <p class="muted" style="margin-bottom:12px">Автообновление каждые 10 сек · заявок: <?= count($rows) ?> · новых: <?= count($free) ?></p>
  <div class="panel">
    <h2>Заявки на <?= h($date) ?></h2>
    <table>
      <thead><tr><th>№</th><th>Контрагент</th><th>Адрес</th><th>Кг</th><th>Статус</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $o): ?>
        <tr>
          <td><?= h($o['number'] ?: $o['external_id']) ?></td>
          <td><?= h($o['partner'] ?? '') ?></td>
          <td><?= h($o['address'] ?? '') ?></td>
          <td><?= number_format((float)$o['weight_kg'], 0, '.', ' ') ?></td>
          <td><?= h($o['status'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <footer class="footer">УТ 11 · автообновление</footer>
</div>
<script src="assets/js/live.js" defer></script>
<script>
document.getElementById('rebuildBtn').addEventListener('click', async () => {
  const btn = document.getElementById('rebuildBtn');
  btn.disabled = true;
  try {
    await fetch('api/rebuild.php?date=<?= urlencode($date) ?>', { method: 'POST' });
    location.reload();
  } catch (e) {
    alert(e.message);
    btn.disabled = false;
  }
});
</script>
</body>
</html>
