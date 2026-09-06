<?php
/**
 * Временный рабочий стол с автообновлением.
 * Полный UI (карта, DnD): восстановить public/index.php из коммита
 * c87a0f682c154b334ad1fcfa4cde760f1d7300e9 и добавить перед </body>:
 * <script src="assets/js/live.js" defer></script>
 */
require __DIR__ . '/bootstrap.php';
$config = require (defined('APP_ROOT') ? APP_ROOT : __DIR__) . '/config.php';
$pdo = Database::pdo();
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
function h(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$orders = $pdo->prepare("SELECT * FROM orders WHERE doc_date = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 300");
$orders->execute([$date]);
$rows = $orders->fetchAll();
$freeN = 0;
foreach ($rows as $o) {
    if (($o['status'] ?? '') === 'new') $freeN++;
}
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
      <form method="get" style="display:flex;gap:8px;align-items:center">
        <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
      </form>
      <button type="button" class="btn btn-ghost" id="rebuildBtn">Пересобрать рейсы</button>
      <a class="btn btn-ghost" href="admin/">Админка</a>
    </div>
  </header>
  <p class="muted" style="margin-bottom:12px">
    Автообновление ~10 сек · заявок: <strong><?= count($rows) ?></strong> · новых: <strong><?= $freeN ?></strong>
  </p>
  <div class="panel">
    <h2>Заявки на <?= h($date) ?></h2>
    <?php if (!$rows): ?>
      <p class="muted">Нет заявок на эту дату</p>
    <?php else: ?>
    <table>
      <thead><tr><th>№</th><th>Контрагент</th><th>Адрес</th><th>Кг</th><th>Статус</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $o): ?>
        <tr>
          <td><?= h($o['number'] ?: $o['external_id']) ?></td>
          <td><?= h($o['partner'] ?? '') ?></td>
          <td><?= h($o['address'] ?? '') ?></td>
          <td><?= number_format((float)($o['weight_kg'] ?? 0), 0, '.', ' ') ?></td>
          <td><?= h($o['status'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <footer class="footer">УТ 11 · Agent+ · live.js</footer>
</div>
<script src="assets/js/live.js" defer></script>
<script>
document.getElementById('rebuildBtn').addEventListener('click', async () => {
  const btn = document.getElementById('rebuildBtn');
  btn.disabled = true;
  btn.textContent = 'Сборка…';
  try {
    const r = await fetch('api/rebuild.php?date=<?= urlencode($date) ?>', { method: 'POST' });
    const data = await r.json();
    if (data.warnings && data.warnings.length) alert(data.warnings.join('\n'));
    location.reload();
  } catch (e) {
    alert(e.message);
    btn.disabled = false;
    btn.textContent = 'Пересобрать рейсы';
  }
});
</script>
</body>
</html>
