-- Полигоны зон (для карты в админке и на рабочем столе)
-- Выполните в phpMyAdmin на БД логистики, если таблица ещё нет.

CREATE TABLE IF NOT EXISTS zone_polygons (
  zone_id INT UNSIGNED NOT NULL PRIMARY KEY,
  polygon MEDIUMTEXT NOT NULL COMMENT 'JSON [[lat,lon],...]',
  color VARCHAR(32) DEFAULT NULL,
  CONSTRAINT fk_zp_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
