<?php

/**
 * Машины в зонах на выбранную дату (из рейсов), без поломки истории.
 * Объединённый рейс «покрывает» все зоны своих заявок — его машина
 * показывается на карточке каждой зоны (с пометкой merged).
 * Пустые рейсы (без заявок) помечаются empty и вместимость зоны не увеличивают.
 */
class DeskVehicles
{
    /**
     * @return array<int, list<array{zone_id:int,vehicle_id:int,name:string,capacity_kg:float|string,empty:bool,merged:bool,note:?string}>>
     */
    public static function byZoneForDate(PDO $pdo, string $date): array
    {
        $vehByZone = [];
        $seenPair = [];

        $st = $pdo->prepare(
            "SELECT t.id AS trip_id, t.zone_id, t.note, v.id AS vehicle_id, v.name, v.capacity_kg
             FROM trips t
             JOIN vehicles v ON v.id = t.vehicle_id
             WHERE t.trip_date = ? AND t.status <> 'cancelled' AND v.is_active = 1
             ORDER BY v.name"
        );
        $st->execute([$date]);
        $trips = $st->fetchAll();

        // Зоны заявок каждого рейса (для объединённых рейсов)
        $itemZoneStmt = $pdo->prepare(
            "SELECT ti.trip_id, o.zone_id
             FROM trip_items ti
             JOIN orders o ON o.id = ti.order_id
             JOIN trips t ON t.id = ti.trip_id
             WHERE t.trip_date = ? AND t.status <> 'cancelled' AND o.zone_id IS NOT NULL"
        );
        $itemZoneStmt->execute([$date]);
        $itemZones = [];
        foreach ($itemZoneStmt->fetchAll() as $row) {
            $itemZones[(int) $row['trip_id']][(int) $row['zone_id']] = true;
        }

        $vehiclesWithTrips = [];
        foreach ($trips as $vr) {
            $vid = (int) $vr['vehicle_id'];
            $vehiclesWithTrips[$vid] = true;
            $loaded = !empty($itemZones[(int) $vr['trip_id']]);
            $zones = [];
            if (!empty($vr['zone_id'])) {
                $zones[(int) $vr['zone_id']] = true;
            }
            if ($loaded) {
                foreach (array_keys($itemZones[(int) $vr['trip_id']]) as $zid) {
                    $zones[$zid] = true;
                }
            }
            $merged = $loaded && count($zones) > 1;

            foreach (array_keys($zones) as $zid) {
                $pair = $zid . ':' . $vid;
                $prev = $seenPair[$pair] ?? null;
                if ($prev !== null && ($prev['loaded'] || !$loaded)) {
                    // В зоне уже показан загруженный рейс машины (или другой пустой) — не дублируем
                    continue;
                }
                $seenPair[$pair] = ['loaded' => $loaded];
                // Заменяем пустой чип машины загруженным, если он был раньше
                $vehByZone[$zid] = array_values(array_filter(
                    $vehByZone[$zid] ?? [],
                    function ($e) use ($vid) {
                        return (int) $e['vehicle_id'] !== $vid;
                    }
                ));
                $vehByZone[$zid][] = [
                    'zone_id' => $zid,
                    'vehicle_id' => $vid,
                    'name' => $vr['name'],
                    'capacity_kg' => $vr['capacity_kg'],
                    'empty' => !$loaded,
                    'merged' => $merged,
                    'note' => $merged ? $vr['note'] : null,
                ];
            }
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
                if (isset($vehiclesWithTrips[$vid])) {
                    continue;
                }
                $pair = (int) $vr['zone_id'] . ':' . $vid;
                if (isset($seenPair[$pair])) {
                    continue;
                }
                $seenPair[$pair] = ['loaded' => true];
                $vehByZone[(int) $vr['zone_id']][] = [
                    'zone_id' => (int) $vr['zone_id'],
                    'vehicle_id' => $vid,
                    'name' => $vr['name'],
                    'capacity_kg' => $vr['capacity_kg'],
                    'empty' => false,
                    'merged' => false,
                    'note' => null,
                ];
            }
        }

        return $vehByZone;
    }
}
