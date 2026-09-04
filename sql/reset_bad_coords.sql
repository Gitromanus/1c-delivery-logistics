-- Сбросить координаты вне Ростовской области (ошибочный геокодинг)
UPDATE orders
SET lat = NULL, lon = NULL
WHERE lat IS NOT NULL
  AND (
    lat < 46.2 OR lat > 48.0
    OR lon < 38.0 OR lon > 43.5
  );
