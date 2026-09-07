<?php

/**
 * Создаёт пустые draft-рейсы на дату для всех активных машин,
 * у которых ещё нет рейса. Нужно для полностью ручной сборки без «Пересобрать».
 */
class EnsureTrips
{
    /**
     * @return array{created: int, existing: int}
     */
    public static function forDate(PDO $pdo, string $date): array
    {
        $vehicles = $pdo->query(
            "SELECT id FROM vehicles WHERE is_active = 1 ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!$vehicles) {
            return ['created' => 0, 'existing' => 0];
        }

        $have = $pdo->prepare(
            "SELECT vehicle_id FROM trips
             WHERE trip_date = ? AND status <> 'cancelled'"
        );
        $have->execute([$date]);
        $existingVehicles = array_map('intval', $have->fetchAll(PDO::FETCH_COLUMN));
        $existingSet = array_flip($existingVehicles);

        // Зона по умолчанию — primary привязка машины (если есть)
        $zoneStmt = $pdo->prepare(
            "SELECT zone_id FROM vehicle_zones
             WHERE vehicle_id = ?
             ORDER BY is_primary DESC, zone_id
             LIMIT 1"
        );

        $ins = $pdo->prepare(
            "INSERT INTO trips (trip_date, vehicle_id, zone_id, status)
             VALUES (?, ?, ?, 'draft')"
        );

        $created = 0;
        foreach ($vehicles as $vid) {
            $vid = (int) $vid;
            if (isset($existingSet[$vid])) {
                continue;
            }
            $zoneStmt->execute([$vid]);
            $zoneId = $zoneStmt->fetchColumn();
            $zoneId = $zoneId !== false ? (int) $zoneId : null;
            $ins->execute([$date, $vid, $zoneId]);
            $created++;
        }

        return [
            'created' => $created,
            'existing' => count($existingVehicles),
        ];
    }
}
