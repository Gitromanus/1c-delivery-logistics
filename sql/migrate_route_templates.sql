SET NAMES utf8mb4;

-- Шаблон порядка объезда клиентов по зоне.
-- Заполняется автоматически: когда оператор перетаскивает заявки в рейсе
-- (drag & drop), порядок клиентов зоны запоминается здесь и используется
-- при следующих сборках рейсов («Пересобрать»).
CREATE TABLE IF NOT EXISTS route_templates (
  zone_id INT UNSIGNED NOT NULL,
  partner VARCHAR(255) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (zone_id, partner),
  CONSTRAINT fk_rt_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
