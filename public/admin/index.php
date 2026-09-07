<?php
/**
 * Обёртка: подключает theme.js на всех экранах админки.
 * Основной код — admin_app.php (если есть) или inline ниже.
 */
session_start();
require dirname(__DIR__) . '/bootstrap.php';
$config = require (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/config.php';

// Если задеплоен полный admin_app.php — используем его
$appFile = __DIR__ . '/admin_app.php';
if (is_file($appFile)) {
    require $appFile;
    exit;
}

$error = '';
if (isset($_POST['password'])) {
    if (hash_equals((string) $config['admin_password'], (string) $_POST['password'])) {
        $_SESSION['admin_ok'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Неверный пароль';
}
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_ok']);
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['admin_ok'])): ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админка — вход</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="../assets/js/theme.js"></script>
</head>
<body>
<div class="app" style="max-width:400px">
  <h1 class="logo" style="margin-bottom:16px">Админка логистики</h1>
  <?php if ($error): ?><div class="flash flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" class="panel">
    <label class="muted">Пароль</label><br>
    <input type="password" name="password" style="width:100%;margin:8px 0 12px" required>
    <button class="btn btn-primary" type="submit">Войти</button>
  </form>
  <p class="muted" style="margin-top:12px"><a href="../">← На рабочий стол</a></p>
</div>
</body>
</html>
<?php
exit;
endif;

// Полная админка должна лежать как admin_app.php.
// Пока её нет — редирект-подсказка.
http_response_code(200);
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Админка</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="../assets/js/theme.js"></script>
</head>
<body>
<div class="app">
  <header class="header">
    <div class="logo">Админка</div>
    <div class="toolbar">
      <a class="btn btn-ghost" href="../">Рабочий стол</a>
      <a class="btn btn-ghost" href="?logout=1">Выход</a>
    </div>
  </header>
  <div class="panel">
    <p>Файл <code>admin_app.php</code> не найден на сервере.</p>
    <p class="muted">Скопируйте текущую полную админку в <code>public/admin/admin_app.php</code>
    и в каждом <code>&lt;head&gt;</code> добавьте:
    <code>&lt;script src="../assets/js/theme.js"&gt;&lt;/script&gt;</code></p>
  </div>
</div>
</body>
</html>
