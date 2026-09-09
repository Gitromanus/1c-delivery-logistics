<?php

/**
 * Постановка заявки в рейс: зона → машина → trip на дату.
 */
class OrderAssign
{
    /**
     * @return array{trip_id: ?int, vehicle_id: ?int, zone_id: ?int}
     */
    public static function toTrip(PDO $pdo, int $orderId, ?int $zoneId, string $docDate, float $weightKg = 0): array
    {
        $result = ['trip_id' => null, 'vehicle_id' => null, 'zone_id' => $zoneId];
        if ($orderId <= 0 || $zoneId === null || $zoneId <= 0) {
            return $result;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $docDate)) {
            $docDate = date('Y-m-d');
        }

        // Уже в рейсе?
        $chk = $pdo->prepare('SELECT trip_id FROM trip_items WHERE order_id = ? LIMIT 1');
        $chk->execute([$orderId]);
        $existingTrip = $chk->fetchColumn();
        if ($existingTrip) {
            $result['trip_id'] = (int) $existingTrip;
            return $result;
        }

        $vehicleId = self::pickVehicle($pdo, $zoneId, $docDate, $weightKg);
        if ($vehicleId === null) {
            return $result;
        }
        $result['vehicle_id'] = $vehicleId;

        $tripId = self::ensureTrip($pdo, $vehicleId, $zoneId, $docDate);
        if ($tripId === null) {
            return $result;
        }
        $result['trip_id'] = $tripId;

        $maxSort = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM trip_items WHERE trip_id = ?');
        $maxSort->execute([$tripId]);
        $sort = ((int) $maxSort->fetchColumn()) + 1;

        $ins = $pdo->prepare(
            'INSERT IGNORE INTO trip_items (trip_id, order_id, sort_order) VALUES (?, ?, ?)'
        );
        $ins->execute([$tripId, $orderId, $sort]);

        $pdo->prepare("UPDATE orders SET status = 'assigned', zone_id = COALESCE(zone_id, ?) WHERE id = ?")
            ->execute([$zoneId, $orderId]);

        return $result;
    }

    private static function pickVehicle(PDO $pdo, int $zoneId, string $docDate, float $weightKg): ?int
    {
        // 1) Рейсы уже в этой зоне на дату — машина с наименьшей загрузкой, куда влезает вес
        $sql = "SELECT t.id AS trip_id, t.vehicle_id, v.capacity_kg,
                       COALESCE((SELECT SUM(o.weight_kg) FROM trip_items ti JOIN orders o ON o.id = ti.order_id WHERE ti.trip_id = t.id), 0) AS loaded
                FROM trips t
                JOIN vehicles v ON v.id = t.vehicle_id
                WHERE t.trip_date = ? AND t.zone_id = ? AND t.status IN ('draft','confirmed')
                  AND v.is_active = 1
                ORDER BY loaded ASC, v.capacity_kg DESC";
        $st = $pdo->prepare($sql);
        $st->execute([$docDate, $zoneId]);
        $rows = $st->fetchAll();
        foreach ($rows as $r) {
            $cap = (float) $r['capacity_kg'];
            $loaded = (float) $r['loaded'];
            if ($cap <= 0 || $loaded + $weightKg <= $cap + 0.01) {
                return (int) $r['vehicle_id'];
            }
        }
        if ($rows) {
            // Все перегружены — всё равно в наименее загруженную
            return (int) $rows[0]['vehicle_id'];
        }

        // 2) Primary машина зоны из vehicle_zones
        $st = $pdo->prepare(
            "SELECT vz.vehicle_id FROM vehicle_zones vz
             JOIN vehicles v ON v.id = vz.vehicle_id
             WHERE vz.zone_id = ? AND v.is_active = 1
             ORDER BY vz.is_primary DESC, v.id
             LIMIT 1"
        );
        $st->execute([$zoneId]);
        $vid = $st->fetchColumn();
        if ($vid) {
            return (int) $vid;
        }

        // 3) Любая активная машина
        $vid = $pdo->query("SELECT id FROM vehicles WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
        return $vid ? (int) $vid : null;
    }

    private static function ensureTrip(PDO $pdo, int $vehicleId, int $zoneId, string $docDate): ?int
    {
        $st = $pdo->prepare(
            "SELECT id FROM trips
             WHERE trip_date = ? AND vehicle_id = ? AND status <> 'cancelled'
             LIMIT 1"
        );
        $st->execute([$docDate, $vehicleId]);
        $id = $st->fetchColumn();
        if ($id) {
            // Подтянуть зону рейса, если пустая
            $pdo->prepare(
                "UPDATE trips SET zone_id = COALESCE(zone_id, ?) WHERE id = ?"
            )->execute([$zoneId, (int) $id]);
            return (int) $id;
        }

        $ins = $pdo->prepare(
            "INSERT INTO trips (trip_date, vehicle_id, zone_id, status) VALUES (?, ?, ?, 'draft')"
        );
        $ins->execute([$docDate, $vehicleId, $zoneId]);
        return (int) $pdo->lastInsertId();
    }
}
