-- Миграция: геометрия зон доставки (полигоны для отрисовки на карте)
-- Выполнить один раз в MySQL.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS zone_polygons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zone_id INT UNSIGNED NOT NULL,
  polygon TEXT NOT NULL COMMENT 'GeoJSON: [[lat,lon], ...]',
  color VARCHAR(20) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_zone (zone_id),
  CONSTRAINT fk_zp_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;