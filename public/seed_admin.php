<?php
/**
 * Одноразово: создать таблицу users (если нет) и пользователя admin/admin.
 * Удалите файл после использования!
 */
require __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo = Database::pdo();

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(150) DEFAULT NULL,
  role ENUM('admin','dispatcher','driver','sales') NOT NULL DEFAULT 'dispatcher',
  vehicle_id INT UNSIGNED DEFAULT NULL,
  zone_id INT UNSIGNED DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_login (login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
  skey VARCHAR(64) NOT NULL PRIMARY KEY,
  svalue TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$hash = password_hash('admin', PASSWORD_DEFAULT);
$st = $pdo->prepare('SELECT id FROM users WHERE login = ?');
$st->execute(['admin']);
if ($st->fetch()) {
    $pdo->prepare('UPDATE users SET password_hash = ?, role = ?, is_active = 1 WHERE login = ?')
        ->execute([$hash, 'admin', 'admin']);
    echo "OK: пароль admin обновлён (login: admin / password: admin)\n";
} else {
    $pdo->prepare('INSERT INTO users (login, password_hash, name, role, is_active) VALUES (?,?,?,?,1)')
        ->execute(['admin', $hash, 'Администратор', 'admin']);
    echo "OK: создан admin / admin\n";
}
echo "Удалите seed_admin.php с сервера!\n";
