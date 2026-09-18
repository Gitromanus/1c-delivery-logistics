<?php

class TripBuilder
{
    public static function rebuild(PDO $pdo, string $date): array
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id FROM trips WHERE trip_date = ? AND status = 'draft'");
            $stmt->execute([$date]);
            $oldIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($oldIds) {
                $in = implode(',', array_map('intval', $oldIds));
                $pdo->exec("DELETE FROM trip_items WHERE trip_id IN ($in)");
                $pdo->exec("DELETE FROM trips WHERE id IN ($in)");
            }

            $pdo->prepare("UPDATE orders SET status = 'new' WHERE doc_date = ? AND status = 'assigned'")->execute([$date]);

            // Порядок заявок: сначала клиенты по сохранённому шаблону зоны
            // (ручной порядок оператора), остальные — в конце, по id.
            $ordersStmt = $pdo->prepare(
                "SELECT o.* FROM orders o
                 LEFT JOIN route_templates rt ON rt.zone_id = o.zone_id AND rt.partner = o.partner
                 WHERE o.doc_date = ? AND o.status IN ('new','assigned')
                 ORDER BY o.zone_id, COALESCE(rt.position, 999999), o.id"
            );
            $ordersStmt->execute([$date]);
            $orders = $ordersStmt->fetchAll();

            $byZone = [];
            foreach ($orders as $o) {
                $zid = $o['zone_id'] ?: 0;
                $byZone[$zid][] = $o;
            }

            // Справочники для объединённых рейсов: зоны и шаблоны порядка.
            $zoneInfo = [];
            foreach ($pdo->query('SELECT id, name, sort_order FROM zones WHERE is_active = 1') as $z) {
                $zoneInfo[(int) $z['id']] = ['name' => $z['name'], 'sort' => (int) $z['sort_order']];
            }
            $tplMap = [];
            try {
                foreach ($pdo->query('SELECT zone_id, partner, position FROM route_templates') as $rt) {
                    $tplMap[(int) $rt['zone_id']][(string) $rt['partner']] = (int) $rt['position'];
                }
            } catch (Throwable $e) {
                // Таблицы шаблонов нет — объединённые рейсы без шаблонного порядка.
            }

            $warnings = [];
            $tripsCreated = 0;

            $ins = $pdo->prepare(
                "INSERT INTO trips (trip_date, vehicle_id, zone_id, status, note) VALUES (?, ?, ?, 'draft', ?)"
            );
            $item = $pdo->prepare('INSERT INTO trip_items (trip_id, order_id, sort_order) VALUES (?, ?, ?)');
            $mark = $pdo->prepare("UPDATE orders SET status = 'assigned' WHERE id = ?");

            // --- Проход 1: совмещённые рейсы ---
            // Машина, привязанная к нескольким зонам, при малой суммарной
            // загрузке везёт все эти зоны одним рейсом. Если не влезает —
            // зоны обрабатываются раздельно (проход 2).
            $multi = $pdo->query(
                "SELECT v.id, v.name, v.capacity_kg, GROUP_CONCAT(vz.zone_id) AS zone_ids
                 FROM vehicles v
                 INNER JOIN vehicle_zones vz ON vz.vehicle_id = v.id
                 INNER JOIN zones z ON z.id = vz.zone_id AND z.is_active = 1
                 WHERE v.is_active = 1
                 GROUP BY v.id, v.name, v.capacity_kg
                 HAVING COUNT(DISTINCT vz.zone_id) >= 2
                 ORDER BY v.capacity_kg DESC, v.id"
            )->fetchAll();

            foreach ($multi as $v) {
                $vZoneIds = array_map('intval', explode(',', (string) $v['zone_ids']));
                $useZones = [];
                foreach ($vZoneIds as $zid) {
                    if (!empty($byZone[$zid])) {
                        $useZones[] = $zid;
                    }
                }
                if (count($useZones) < 2) {
                    continue;
                }

                $pool = [];
                $weight = 0.0;
                foreach ($useZones as $zid) {
                    foreach ($byZone[$zid] as $o) {
                        $pool[] = $o;
                        $weight += (float) $o['weight_kg'];
                    }
                }
                if ($weight > (float) $v['capacity_kg'] + 0.0001) {
                    continue;
                }

                usort($useZones, function ($a, $b) use ($zoneInfo) {
                    return (($zoneInfo[$a]['sort'] ?? 999) <=> ($zoneInfo[$b]['sort'] ?? 999))
                        ?: ($a <=> $b);
                });
                usort($pool, function ($a, $b) use ($zoneInfo, $tplMap) {
                    $za = (int) $a['zone_id'];
                    $zb = (int) $b['zone_id'];
                    if ($za !== $zb) {
                        return (($zoneInfo[$za]['sort'] ?? 999) <=> ($zoneInfo[$zb]['sort'] ?? 999));
                    }
                    $pa = $tplMap[$za][(string) $a['partner']] ?? 999999;
                    $pb = $tplMap[$zb][(string) $b['partner']] ?? 999999;
                    if ($pa !== $pb) {
                        return $pa <=> $pb;
                    }
                    return (int) $a['id'] <=> (int) $b['id'];
                });

                $names = implode(' + ', array_map(function ($zid) use ($zoneInfo) {
                    return $zoneInfo[$zid]['name'] ?? ('Зона #' . $zid);
                }, $useZones));

                $ins->execute([$date, $v['id'], $useZones[0], 'Объединённый рейс: ' . $names]);
                $tripId = (int) $pdo->lastInsertId();
                $tripsCreated++;

                $i = 1;
                foreach ($pool as $o) {
                    $item->execute([$tripId, $o['id'], $i++]);
                    $mark->execute([$o['id']]);
                }
                foreach ($useZones as $zid) {
                    $byZone[$zid] = [];
                }

                $warnings[] = sprintf(
                    'Объединённый рейс (%s): %s, %.0f / %.0f кг',
                    $names,
                    $v['name'],
                    $weight,
                    (float) $v['capacity_kg']
                );
            }

            // --- Проход 2: обычные рейсы по зонам ---
            foreach ($byZone as $zoneId => $zoneOrders) {
                if (!(int) $zoneId) {
                    if ($zoneOrders) {
                        $warnings[] = 'Есть заявки без зоны: ' . count($zoneOrders) . ' шт.';
                    }
                    continue;
                }
                if (!$zoneOrders) {
                    continue;
                }

                $vehStmt = $pdo->prepare(
                    "SELECT v.* FROM vehicles v
                     INNER JOIN vehicle_zones vz ON vz.vehicle_id = v.id
                     WHERE vz.zone_id = ? AND v.is_active = 1
                     ORDER BY vz.is_primary DESC, v.capacity_kg DESC"
                );
                $vehStmt->execute([$zoneId]);
                $vehicles = $vehStmt->fetchAll();

                if (!$vehicles) {
                    $warnings[] = "Зона #$zoneId: нет привязанных машин";
                    continue;
                }

                $queue = $zoneOrders;
                foreach ($vehicles as $vehicle) {
                    if (!$queue) {
                        break;
                    }
                    $capacity = (float) $vehicle['capacity_kg'];
                    $used = 0;
                    $batch = [];

                    foreach ($queue as $idx => $order) {
                        $w = (float) $order['weight_kg'];
                        if ($used + $w <= $capacity + 0.0001) {
                            $batch[] = $order;
                            $used += $w;
                            unset($queue[$idx]);
                        }
                    }
                    $queue = array_values($queue);

                    if (!$batch) {
                        if ($queue) {
                            $batch[] = $queue[0];
                            $used = (float) $queue[0]['weight_kg'];
                            array_shift($queue);
                            $warnings[] = sprintf(
                                'ТС %s: заявка %s превышает вместимость',
                                $vehicle['name'],
                                $batch[0]['number'] ?: $batch[0]['external_id']
                            );
                        } else {
                            break;
                        }
                    }

                    $ins->execute([$date, $vehicle['id'], $zoneId, null]);
                    $tripId = (int) $pdo->lastInsertId();
                    $tripsCreated++;

                    foreach ($batch as $i => $order) {
                        $item->execute([$tripId, $order['id'], $i + 1]);
                        $mark->execute([$order['id']]);
                    }

                    if ($used > $capacity) {
                        $warnings[] = sprintf(
                            '%s: перегруз +%.0f кг (%.0f / %.0f)',
                            $vehicle['name'],
                            $used - $capacity,
                            $used,
                            $capacity
                        );
                    }
                }

                if ($queue) {
                    $leftW = array_sum(array_map(function ($o) {
                        return (float) $o['weight_kg'];
                    }, $queue));
                    $warnings[] = sprintf(
                        'Зона #%d: не влезло заявок %d (≈%.0f кг). Нужна ещё машина.',
                        $zoneId,
                        count($queue),
                        $leftW
                    );
                }
            }

            $pdo->commit();
            return ['ok' => true, 'trips' => $tripsCreated, 'warnings' => $warnings];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
