<?php
require dirname(__DIR__) . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireAdmin('../login.php');
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && class_exists('Settings')) {
    $keys = ['api_key', 'yandex_maps_key', 'yandex_geocoder_key', 'dadata_token'];
    foreach ($keys as $k) {
        if (array_key_exists($k, $_POST)) {
            Settings::set($k, trim((string) $_POST[$k]));
        }
    }
    $msg = 'Настройки сохранены (в БД app_settings)';
}

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
$api = class_exists('Settings') ? Settings::get('api_key', '') : '';
$ym = class_exists('Settings') ? Settings::get('yandex_maps_key', '') : '';
$yg = class_exists('Settings') ? Settings::get('yandex_geocoder_key', '') : '';
$dd = class_exists('Settings') ? Settings::get('dadata_token', '') : '';
// fallback display from config if empty
$cfg = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';
if ($api === '') $api = (string)($cfg['api_key'] ?? $cfg['ApiKey1s'] ?? '');
if ($ym === '') $ym = (string)($cfg['yandex_maps_key'] ?? '');
if ($yg === '') $yg = (string)($cfg['yandex_geocoder_key'] ?? '');
if ($dd === '') $dd = (string)($cfg['dadata_token'] ?? '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Настройки API</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="../assets/js/theme.js"></script>
</head>
<body>
<div class="app">
  <div class="admin-nav">
    <a href="index.php">← Админка</a>
    <a href="users.php">Пользователи</a>
    <a href="settings.php">Настройки API</a>
    <a href="../index.php">Рабочий стол</a>
    <a href="../logout.php">Выйти</a>
  </div>
  <h1>Ключи API</h1>
  <?php if ($msg): ?><div class="flash flash-ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash flash-err"><?= h($err) ?></div><?php endif; ?>
  <section class="panel" style="max-width:560px">
    <form method="post" style="display:grid;gap:10px">
      <label>API-ключ для 1С (X-Api-Key)
        <input type="text" name="api_key" value="<?= h($api) ?>" autocomplete="off">
      </label>
      <label>Yandex Maps JS API
        <input type="text" name="yandex_maps_key" value="<?= h($ym) ?>" autocomplete="off">
      </label>
      <label>Yandex Geocoder HTTP (если есть)
        <input type="text" name="yandex_geocoder_key" value="<?= h($yg) ?>" autocomplete="off">
      </label>
      <label>DaData token
        <input type="text" name="dadata_token" value="<?= h($dd) ?>" autocomplete="off">
      </label>
      <button class="btn btn-primary" type="submit">Сохранить</button>
    </form>
    <p class="muted" style="margin-top:12px;font-size:0.85rem">
      Значения пишутся в таблицу <code>app_settings</code> и перекрывают config.php.
      Сначала выполните <code>/seed_admin.php</code>, если таблиц ещё нет.
    </p>
  </section>
</div>
</body>
</html>
