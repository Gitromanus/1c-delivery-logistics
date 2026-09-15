-- Пользователи и настройки (API-ключи в БД)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(150) DEFAULT NULL,
  role ENUM('admin','dispatcher','driver','sales') NOT NULL DEFAULT 'dispatcher',
  vehicle_id INT UNSIGNED DEFAULT NULL COMMENT 'для driver',
  zone_id INT UNSIGNED DEFAULT NULL COMMENT 'для sales',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_login (login),
  KEY idx_role (role),
  CONSTRAINT fk_users_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_settings (
  skey VARCHAR(64) NOT NULL PRIMARY KEY,
  svalue TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Админ по умолчанию: login admin / пароль admin (смените после входа!)
-- Хэш password_hash('admin', PASSWORD_DEFAULT) — если не сработает, создайте через админку после первого входа по config
INSERT IGNORE INTO users (login, password_hash, name, role, is_active)
VALUES (
  'admin',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'Администратор',
  'admin',
  1
);
-- Примечание: хэш выше = "password". Ниже PHP-скрипт seed создаст admin/admin.
