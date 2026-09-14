<?php

/**
 * Постановка заявки в рейс: зона → машина → trip на дату.
 * В ответе — загрузка рейса и флаг перегруза.
 */
class OrderAssign
{
    /**
     * @return array{
     *   trip_id: ?int,
     *   vehicle_id: ?int,
     *   zone_id: ?int,
     *   vehicle_name: ?string,
     *   capacity_kg: float,
     *   loaded_kg: float,
     *   free_kg: float,
     *   overload: bool,
     *   overload_kg: float,
     *   load_percent: int,
     *   message: string
     * }
     */
    public static function toTrip(PDO $pdo, int $orderId, ?int $zoneId, string $docDate, float $weightKg = 0): array
    {
        $result = self::emptyResult($zoneId);
        if ($orderId <= 0 || $zoneId === null || $zoneId <= 0) {
            $result['message'] = 'Зона не определена — заявка без рейса';
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
            return array_merge($result, self::tripLoad($pdo, (int) $existingTrip));
        }

        $vehicleId = self::pickVehicle($pdo, $zoneId, $docDate, $weightKg);
        if ($vehicleId === null) {
            $result['message'] = 'Нет доступной машины для зоны';
            return $result;
        }
        $result['vehicle_id'] = $vehicleId;

        $tripId = self::ensureTrip($pdo, $vehicleId, $zoneId, $docDate);
        if ($tripId === null) {
            $result['message'] = 'Не удалось создать рейс';
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

        $load = self::tripLoad($pdo, $tripId);
        return array_merge($result, $load);
    }

    private static function emptyResult(?int $zoneId): array
    {
        return [
            'trip_id' => null,
            'vehicle_id' => null,
            'zone_id' => $zoneId,
            'vehicle_name' => null,
            'capacity_kg' => 0.0,
            'loaded_kg' => 0.0,
            'free_kg' => 0.0,
            'overload' => false,
            'overload_kg' => 0.0,
            'load_percent' => 0,
            'message' => '',
        ];
    }

    /** Загрузка рейса после постановки заявки. */
    public static function tripLoad(PDO $pdo, int $tripId): array
    {
        $st = $pdo->prepare(
            "SELECT t.vehicle_id, v.name AS vehicle_name, v.capacity_kg,
                    COALESCE((SELECT SUM(o.weight_kg) FROM trip_items ti
                              JOIN orders o ON o.id = ti.order_id WHERE ti.trip_id = t.id), 0) AS loaded_kg
             FROM trips t
             JOIN vehicles v ON v.id = t.vehicle_id
             WHERE t.id = ?"
        );
        $st->execute([$tripId]);
        $row = $st->fetch();
        if (!$row) {
            return [
                'vehicle_id' => null,
                'vehicle_name' => null,
                'capacity_kg' => 0.0,
                'loaded_kg' => 0.0,
                'free_kg' => 0.0,
                'overload' => false,
                'overload_kg' => 0.0,
                'load_percent' => 0,
                'message' => 'Рейс не найден',
            ];
        }

        $cap = (float) $row['capacity_kg'];
        $loaded = (float) $row['loaded_kg'];
        $free = $cap - $loaded;
        $overload = $loaded > $cap + 0.01;
        $overloadKg = $overload ? round($loaded - $cap, 2) : 0.0;
        $pct = $cap > 0 ? (int) min(999, round($loaded / $cap * 100)) : 0;

        if ($overload) {
            $msg = sprintf(
                'ПЕРЕГРУЗ: %s — загружено %.0f из %.0f кг (+%.0f кг, %d%%)',
                $row['vehicle_name'],
                $loaded,
                $cap,
                $overloadKg,
                $pct
            );
        } else {
            $msg = sprintf(
                '%s — загружено %.0f из %.0f кг (свободно %.0f кг, %d%%)',
                $row['vehicle_name'],
                $loaded,
                $cap,
                max(0, $free),
                $pct
            );
        }

        return [
            'trip_id' => $tripId,
            'vehicle_id' => (int) $row['vehicle_id'],
            'vehicle_name' => (string) $row['vehicle_name'],
            'capacity_kg' => $cap,
            'loaded_kg' => $loaded,
            'free_kg' => round($free, 2),
            'overload' => $overload,
            'overload_kg' => $overloadKg,
            'load_percent' => $pct,
            'message' => $msg,
        ];
    }

    private static function pickVehicle(PDO $pdo, int $zoneId, string $docDate, float $weightKg): ?int
    {
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
            return (int) $rows[0]['vehicle_id'];
        }

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
