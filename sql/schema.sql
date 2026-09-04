SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS zones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(50) DEFAULT NULL,
  keywords VARCHAR(500) DEFAULT NULL COMMENT 'Подстроки адреса через ; для авто-зоны',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  plate VARCHAR(50) DEFAULT NULL,
  capacity_kg DECIMAL(12,2) NOT NULL DEFAULT 1000,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicle_zones (
  vehicle_id INT UNSIGNED NOT NULL,
  zone_id INT UNSIGNED NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (vehicle_id, zone_id),
  CONSTRAINT fk_vz_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  CONSTRAINT fk_vz_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  external_id VARCHAR(100) NOT NULL,
  number VARCHAR(50) DEFAULT NULL,
  doc_date DATE DEFAULT NULL,
  partner VARCHAR(255) DEFAULT NULL,
  address VARCHAR(500) NOT NULL,
  weight_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount DECIMAL(15,2) DEFAULT NULL,
  comment VARCHAR(500) DEFAULT NULL,
  zone_id INT UNSIGNED DEFAULT NULL,
  status ENUM('new','assigned','done','cancelled') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_external (external_id),
  KEY idx_date_status (doc_date, status),
  KEY idx_zone (zone_id),
  CONSTRAINT fk_orders_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trips (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_date DATE NOT NULL,
  vehicle_id INT UNSIGNED NOT NULL,
  zone_id INT UNSIGNED DEFAULT NULL,
  status ENUM('draft','confirmed','done','cancelled') NOT NULL DEFAULT 'draft',
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_trip_date (trip_date),
  CONSTRAINT fk_trips_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
  CONSTRAINT fk_trips_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trip_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_trip_order (trip_id, order_id),
  CONSTRAINT fk_ti_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  CONSTRAINT fk_ti_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Демо-данные (можно удалить)
INSERT INTO zones (name, code, keywords, sort_order) VALUES
('Молодёжный', 'molodezhny', 'Молодёжн;Молодежн', 10),
('Донской', 'donskoy', 'Донск', 20),
('Новочеркасск', 'novocherkassk', 'Новочеркасск', 30),
('Шахты', 'shahty', 'Шахты', 40);

INSERT INTO vehicles (name, plate, capacity_kg) VALUES
('Газель А123АА', 'А123АА', 900),
('Газель В456ВВ', 'В456ВВ', 900),
('Фургон С789СС', 'С789СС', 1500);

INSERT INTO vehicle_zones (vehicle_id, zone_id, is_primary) VALUES
(1, 1, 1),
(2, 2, 1),
(2, 1, 0),
(3, 3, 1);

SET FOREIGN_KEY_CHECKS = 1;
