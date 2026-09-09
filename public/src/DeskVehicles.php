<?php

/**
 * Машины в зонах на выбранную дату (из рейсов), без поломки истории.
 */
class DeskVehicles
{
    /**
     * @return array<int, list<array{zone_id:int,vehicle_id:int,name:string,capacity_kg:float|string}>>
     */
    public static function byZoneForDate(PDO $pdo, string $date): array
    {
        $vehByZone = [];
        $seenVeh = [];

        $st = $pdo->prepare(
            "SELECT t.zone_id, v.id AS vehicle_id, v.name, v.capacity_kg
             FROM trips t
             JOIN vehicles v ON v.id = t.vehicle_id
             WHERE t.trip_date = ? AND t.status <> 'cancelled' AND t.zone_id IS NOT NULL
               AND v.is_active = 1
             ORDER BY v.name"
        );
        $st->execute([$date]);
        foreach ($st->fetchAll() as $vr) {
            $vid = (int) $vr['vehicle_id'];
            if (isset($seenVeh[$vid])) {
                continue;
            }
            $seenVeh[$vid] = true;
            $vehByZone[(int) $vr['zone_id']][] = $vr;
        }

        // Сегодня: машины без рейса — по vehicle_zones (шаблон по умолчанию)
        if ($date === date('Y-m-d')) {
            $rows = $pdo->query(
                "SELECT vz.zone_id, v.id AS vehicle_id, v.name, v.capacity_kg
                 FROM vehicle_zones vz
                 JOIN vehicles v ON v.id = vz.vehicle_id
                 WHERE v.is_active = 1
                 ORDER BY vz.is_primary DESC, v.name"
            )->fetchAll();
            foreach ($rows as $vr) {
                $vid = (int) $vr['vehicle_id'];
                if (isset($seenVeh[$vid])) {
                    continue;
                }
                $seenVeh[$vid] = true;
                $vehByZone[(int) $vr['zone_id']][] = $vr;
            }
        }

        return $vehByZone;
    }
}
