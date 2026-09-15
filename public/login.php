<?php
require __DIR__ . '/bootstrap.php';

if (class_exists('Auth') && Auth::check()) {
    $role = Auth::role();
    if (in_array($role, ['admin', 'dispatcher'], true) || !empty($_SESSION['config_admin'])) {
        $next = $_GET['next'] ?? 'index.php';
    } else {
        $next = 'index.php';
    }
    header('Location: ' . $next);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string) ($_POST['login'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    if (class_exists('Auth') && Auth::attempt($login, $pass)) {
        $next = $_POST['next'] ?? $_GET['next'] ?? 'index.php';
        if (!is_string($next) || strpos($next, '://') !== false || strpos($next, '..') !== false) {
            $next = 'index.php';
        }
        header('Location: ' . $next);
        exit;
    }
    $error = 'Неверный логин или пароль';
}

$next = htmlspecialchars($_GET['next'] ?? 'index.php', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Вход — Логистика</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 16px; }
    .login-box {
      width: 100%; max-width: 380px;
      background: var(--panel); border: 1px solid var(--border);
      border-radius: 12px; padding: 24px; box-shadow: var(--shadow);
    }
    .login-box h1 { font-size: 1.2rem; margin-bottom: 8px; }
    .login-box p.muted { margin-bottom: 16px; font-size: 0.85rem; color: var(--muted); }
    .login-box label { display: block; font-size: 0.8rem; color: var(--muted); margin: 12px 0 4px; }
    .login-box input { width: 100%; }
    .login-box .btn { width: 100%; margin-top: 18px; }
    .err { background: rgba(248,113,113,.12); color: var(--danger); padding: 10px; border-radius: 8px; margin-bottom: 12px; font-size: 0.9rem; }
  </style>
</head>
<body>
  <div class="login-box">
    <h1>Логистика доставки</h1>
    <p class="muted">Вход для диспетчера, водителя и торгового представителя</p>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="next" value="<?= $next ?>">
      <label>Логин</label>
      <input type="text" name="login" required autocomplete="username" autofocus>
      <label>Пароль</label>
      <input type="password" name="password" required autocomplete="current-password">
      <button type="submit" class="btn btn-primary">Войти</button>
    </form>
  </div>
</body>
</html>
