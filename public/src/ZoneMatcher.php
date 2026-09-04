<?php

/**
 * Определение зоны по координатам заявки (попадание точки в полигон зоны).
 * Полигоны рисуются в админке и хранятся в zone_polygons.polygon как [[lat,lon],...].
 */
class ZoneMatcher
{
    /**
     * Возвращает id зоны, чей полигон содержит точку (lat, lon).
     * Если точка попадает в несколько полигонов — берётся наименьший по площади.
     */
    public static function matchByCoords(PDO $pdo, ?float $lat, ?float $lon): ?int
    {
        if ($lat === null || $lon === null) {
            return null;
        }

        $rows = $pdo->query(
            "SELECT zp.zone_id, zp.polygon
             FROM zone_polygons zp
             JOIN zones z ON z.id = zp.zone_id
             WHERE z.is_active = 1"
        )->fetchAll();

        $best = null; // [zone_id, area]
        foreach ($rows as $row) {
            $poly = json_decode((string) $row['polygon'], true);
            if (!is_array($poly) || count($poly) < 3) {
                continue;
            }
            if (!self::pointInPolygon($lat, $lon, $poly)) {
                continue;
            }
            $area = self::polygonArea($poly);
            if ($best === null || $area < $best[1]) {
                $best = [(int) $row['zone_id'], $area];
            }
        }

        return $best ? $best[0] : null;
    }

    /** Попадание точки [lat, lon] в многоугольник [[lat,lon],...] (Ray casting). */
    private static function pointInPolygon(float $lat, float $lon, array $poly): bool
    {
        $inside = false;
        $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float) $poly[$i][1]; // lon
            $yi = (float) $poly[$i][0]; // lat
            $xj = (float) $poly[$j][1];
            $yj = (float) $poly[$j][0];
            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    /** Площадь многоугольника (формула шнурков). */
    private static function polygonArea(array $poly): float
    {
        $area = 0.0;
        $n = count($poly);
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $area += (float) $poly[$i][0] * (float) $poly[$j][1]
                   - (float) $poly[$j][0] * (float) $poly[$i][1];
        }
        return abs($area) / 2;
    }
}
