<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админка логистики</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="../assets/js/theme.js"></script>
  <?php if (!empty($config['yandex_maps_key'])): ?>
  <script src="https://api-maps.yandex.ru/2.1/?apikey=<?= htmlspecialchars($config['yandex_maps_key']) ?>&lang=ru_RU"></script>
  <?php endif; ?>
</head>
<body>
<div class="app">
  <header class="header">
    <div class="logo">Админка</div>
    <div class="toolbar">
      <a class="btn btn-ghost<?= $tab==='main'?' btn-primary':'' ?>" href="?tab=main">Зоны и ТС</a>
      <a class="btn btn-ghost<?= $tab==='users'?' btn-primary':'' ?>" href="?tab=users">Пользователи</a>
      <a class="btn btn-ghost<?= $tab==='settings'?' btn-primary':'' ?>" href="?tab=settings">API</a>
      <a class="btn btn-ghost" href="../">Рабочий стол</a>
      <a class="btn btn-ghost" href="../logout.php">Выйти</a>
    </div>
  </header>
  <?php if ($msg): ?><div class="flash flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if (!empty($err)): ?><div class="flash flash-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<?php if ($tab === 'main'): ?>
  <div class="grid" style="grid-template-columns:1fr 1fr 1fr">
    <section class="panel">
      <div class="panel-head"><h2>Зоны</h2>
        <button class="btn btn-ghost btn-sm" type="button" onclick="openZoneAdd()">+ Добавить</button></div>
      <table><tr><th>Название</th><th></th></tr>
        <?php foreach ($zones as $z): ?>
          <tr>
            <td><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:<?= htmlspecialchars(!empty($z['poly_color']) ? $z['poly_color'] : '#ccc') ?>;margin-right:6px;vertical-align:middle"></span><?= htmlspecialchars($z['name']) ?></td>
            <td style="text-align:right;white-space:nowrap">
              <button type="button" class="btn-icon" onclick="editZone(<?= (int)$z['id'] ?>, '<?= htmlspecialchars((string)$z['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string)($z['code']??''), ENT_QUOTES) ?>', <?= (int)($z['sort_order']??0) ?>)">✎</button>
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
            <td><?= htmlspecialchars($v['name']) ?><?= !empty($v['plate']) ? ' · '.htmlspecialchars($v['plate']) : '' ?></td>
            <td><?= number_format((float)$v['capacity_kg'], 0, '.', ' ') ?></td>
            <td style="text-align:right;white-space:nowrap">
              <button type="button" class="btn-icon" onclick="editVehicle(<?= (int)$v['id'] ?>, '<?= htmlspecialchars($v['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars((string)($v['plate']??''), ENT_QUOTES) ?>', <?= (float)$v['capacity_kg'] ?>)">✎</button>
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
            <td><?= htmlspecialchars($b['vname'] ?? $b['vehicle_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($b['zname'] ?? $b['zone_name'] ?? '') ?><?= !empty($b['is_primary']) ? ' ★' : '' ?></td>
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
      <p class="muted">Укажите ключ на вкладке <a href="?tab=settings">API</a></p>
    <?php else: ?>
      <div id="zpMap" class="map-box" style="height:480px"></div>
      <p class="muted" style="margin-top:8px">Выберите зону → «Рисовать заново»: кликами вершины, двойной клик завершает → «Сохранить».</p>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($tab === 'users'): ?>
  <section class="panel">
    <div class="panel-head"><h2>Пользователи</h2>
      <button class="btn btn-ghost btn-sm" type="button" onclick="openUserAdd()">+ Добавить</button></div>
    <table>
      <tr><th>Логин</th><th>Имя</th><th>Роль</th><th>Машина / зона</th><th>Активен</th><th></th></tr>
      <?php if (!$users): ?>
        <tr><td colspan="6" class="muted">Нет пользователей. Нажмите «+ Добавить» или /seed_admin.php</td></tr>
      <?php endif; ?>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['login']) ?></td>
          <td><?= htmlspecialchars($u['name'] ?? '') ?></td>
          <td><?= htmlspecialchars($roleLabels[$u['role']] ?? $u['role']) ?></td>
          <td><?= htmlspecialchars($u['vname'] ?? $u['zname'] ?? '—') ?></td>
          <td><?= (int)$u['is_active'] ? 'да' : 'нет' ?></td>
          <td style="text-align:right;white-space:nowrap">
            <button type="button" class="btn-icon" title="Изменить" onclick='editUser(<?= (int)$u["id"] ?>, <?= json_encode($u["login"], JSON_UNESCAPED_UNICODE) ?>, <?= json_encode((string)($u["name"] ?? ""), JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($u["role"], JSON_UNESCAPED_UNICODE) ?>, <?= (int)($u["vehicle_id"] ?? 0) ?>, <?= (int)($u["zone_id"] ?? 0) ?>, <?= (int)$u["is_active"] ?>)'>✎</button>
            <?php if ($u['login'] !== 'admin'): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="btn-del">✕</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </section>
<?php endif; ?>
