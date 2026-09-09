<?php

/**
 * Зона по координатам (полигон) или по ключевым словам адреса.
 */
class ZoneMatcher
{
    /**
     * id зоны, чей полигон содержит точку. При нескольких — меньшая площадь.
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

        $best = null;
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

    /**
     * Зона по подстрокам адреса (zones.keywords через ;).
     * Берётся зона с самым длинным совпавшим ключевым словом.
     */
    public static function matchZoneId(PDO $pdo, string $address): ?int
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }
        $addrLower = mb_strtolower($address);

        try {
            $rows = $pdo->query(
                "SELECT id, keywords FROM zones WHERE is_active = 1 AND keywords IS NOT NULL AND keywords <> ''"
            )->fetchAll();
        } catch (Throwable $e) {
            return null;
        }

        $bestId = null;
        $bestLen = 0;
        foreach ($rows as $row) {
            $parts = preg_split('/[;|\n]+/u', (string) $row['keywords']);
            if (!$parts) {
                continue;
            }
            foreach ($parts as $kw) {
                $kw = trim(mb_strtolower($kw));
                if ($kw === '' || mb_strlen($kw) < 2) {
                    continue;
                }
                if (mb_strpos($addrLower, $kw) !== false && mb_strlen($kw) > $bestLen) {
                    $bestLen = mb_strlen($kw);
                    $bestId = (int) $row['id'];
                }
            }
        }

        return $bestId;
    }

    private static function pointInPolygon(float $lat, float $lon, array $poly): bool
    {
        $inside = false;
        $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float) $poly[$i][1];
            $yi = (float) $poly[$i][0];
            $xj = (float) $poly[$j][1];
            $yj = (float) $poly[$j][0];
            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

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
