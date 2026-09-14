<?php

/**
 * Постановка / снятие заявки с рейса.
 */
class OrderAssign
{
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

        return array_merge($result, self::tripLoad($pdo, $tripId));
    }

    /**
     * Снять с рейса по id заявки.
     */
    public static function unassign(PDO $pdo, int $orderId): array
    {
        $result = self::emptyResult(null);
        $result['unassigned'] = true;
        $result['deleted_links'] = 0;

        if ($orderId <= 0) {
            $result['message'] = 'order_id required';
            return $result;
        }

        $del = $pdo->prepare('DELETE FROM trip_items WHERE order_id = ?');
        $del->execute([$orderId]);
        $deleted = (int) $del->rowCount();

        $pdo->prepare("UPDATE orders SET status = 'new' WHERE id = ?")->execute([$orderId]);

        $st = $pdo->prepare('SELECT zone_id, external_id FROM orders WHERE id = ?');
        $st->execute([$orderId]);
        $row = $st->fetch();
        if ($row) {
            $result['zone_id'] = $row['zone_id'] !== null ? (int) $row['zone_id'] : null;
            $result['external_id'] = $row['external_id'];
        }

        $result['order_id'] = $orderId;
        $result['deleted_links'] = $deleted;
        $result['message'] = $deleted > 0
            ? 'Снята с рейса → нераспределённые'
            : 'Уже была вне рейса → нераспределённые';

        return $result;
    }

    /**
     * Снять по external_id (GUID из 1С), даже если order_id ещё не знаем.
     */
    public static function unassignByExternalId(PDO $pdo, string $externalId): array
    {
        $externalId = mb_substr(trim($externalId), 0, 100);
        $result = self::emptyResult(null);
        $result['unassigned'] = true;
        $result['deleted_links'] = 0;

        if ($externalId === '') {
            $result['message'] = 'external_id required';
            return $result;
        }

        $st = $pdo->prepare('SELECT id, zone_id FROM orders WHERE external_id = ? LIMIT 1');
        $st->execute([$externalId]);
        $row = $st->fetch();
        if (!$row) {
            $result['message'] = 'Заявка не найдена на сайте';
            return $result;
        }

        $orderId = (int) $row['id'];
        return self::unassign($pdo, $orderId);
    }

    private static function emptyResult(?int $zoneId): array
    {
        return [
            'trip_id' => null,
            'vehicle_id' => null,
            'zone_id' => $zoneId,
            'zone_name' => null,
            'vehicle_name' => null,
            'vehicle_plate' => null,
            'capacity_kg' => 0.0,
            'loaded_kg' => 0.0,
            'free_kg' => 0.0,
            'overload' => false,
            'overload_kg' => 0.0,
            'load_percent' => 0,
            'message' => '',
            'unassigned' => false,
            'deleted_links' => 0,
        ];
    }

    public static function tripLoad(PDO $pdo, int $tripId): array
    {
        $st = $pdo->prepare(
            "SELECT t.vehicle_id, t.zone_id,
                    v.name AS vehicle_name, v.plate AS vehicle_plate, v.capacity_kg,
                    z.name AS zone_name,
                    COALESCE((SELECT SUM(o.weight_kg) FROM trip_items ti
                              JOIN orders o ON o.id = ti.order_id WHERE ti.trip_id = t.id), 0) AS loaded_kg
             FROM trips t
             JOIN vehicles v ON v.id = t.vehicle_id
             LEFT JOIN zones z ON z.id = t.zone_id
             WHERE t.id = ?"
        );
        $st->execute([$tripId]);
        $row = $st->fetch();
        if (!$row) {
            return [
                'vehicle_id' => null,
                'zone_id' => null,
                'zone_name' => null,
                'vehicle_name' => null,
                'vehicle_plate' => null,
                'capacity_kg' => 0.0,
                'loaded_kg' => 0.0,
                'free_kg' => 0.0,
                'overload' => false,
                'overload_kg' => 0.0,
                'load_percent' => 0,
                'message' => 'Рейс не найден',
                'unassigned' => false,
                'deleted_links' => 0,
            ];
        }

        $cap = (float) $row['capacity_kg'];
        $loaded = (float) $row['loaded_kg'];
        $free = $cap - $loaded;
        $overload = $loaded > $cap + 0.01;
        $overloadKg = $overload ? round($loaded - $cap, 2) : 0.0;
        $pct = $cap > 0 ? (int) min(999, round($loaded / $cap * 100)) : 0;

        $name = trim((string) $row['vehicle_name']);
        $plate = trim((string) ($row['vehicle_plate'] ?? ''));
        $zoneName = trim((string) ($row['zone_name'] ?? ''));

        $vehLabel = $name;
        if ($plate !== '') {
            $vehLabel .= ' (' . $plate . ')';
        }
        $zonePart = $zoneName !== '' ? ' · зона «' . $zoneName . '»' : '';

        if ($overload) {
            $msg = sprintf(
                'ПЕРЕГРУЗ: %s%s — загружено %.0f из %.0f кг (+%.0f кг, %d%%)',
                $vehLabel,
                $zonePart,
                $loaded,
                $cap,
                $overloadKg,
                $pct
            );
        } else {
            $msg = sprintf(
                '%s%s — загружено %.0f из %.0f кг (свободно %.0f кг, %d%%)',
                $vehLabel,
                $zonePart,
                $loaded,
                $cap,
                max(0, $free),
                $pct
            );
        }

        return [
            'trip_id' => $tripId,
            'vehicle_id' => (int) $row['vehicle_id'],
            'zone_id' => $row['zone_id'] !== null ? (int) $row['zone_id'] : null,
            'zone_name' => $zoneName !== '' ? $zoneName : null,
            'vehicle_name' => $name,
            'vehicle_plate' => $plate !== '' ? $plate : null,
            'capacity_kg' => $cap,
            'loaded_kg' => $loaded,
            'free_kg' => round($free, 2),
            'overload' => $overload,
            'overload_kg' => $overloadKg,
            'load_percent' => $pct,
            'message' => $msg,
            'unassigned' => false,
            'deleted_links' => 0,
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
