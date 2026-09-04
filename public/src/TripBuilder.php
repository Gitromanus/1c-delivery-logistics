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

            $ordersStmt = $pdo->prepare(
                "SELECT * FROM orders WHERE doc_date = ? AND status IN ('new','assigned') ORDER BY zone_id, id"
            );
            $ordersStmt->execute([$date]);
            $orders = $ordersStmt->fetchAll();

            $byZone = [];
            foreach ($orders as $o) {
                $zid = $o['zone_id'] ?: 0;
                $byZone[$zid][] = $o;
            }

            $warnings = [];
            $tripsCreated = 0;

            foreach ($byZone as $zoneId => $zoneOrders) {
                if (!(int) $zoneId) {
                    $warnings[] = 'Есть заявки без зоны: ' . count($zoneOrders) . ' шт.';
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

                    $ins = $pdo->prepare(
                        "INSERT INTO trips (trip_date, vehicle_id, zone_id, status) VALUES (?, ?, ?, 'draft')"
                    );
                    $ins->execute([$date, $vehicle['id'], $zoneId]);
                    $tripId = (int) $pdo->lastInsertId();
                    $tripsCreated++;

                    $item = $pdo->prepare('INSERT INTO trip_items (trip_id, order_id, sort_order) VALUES (?, ?, ?)');
                    $upd = $pdo->prepare("UPDATE orders SET status = 'assigned' WHERE id = ?");
                    foreach ($batch as $i => $order) {
                        $item->execute([$tripId, $order['id'], $i + 1]);
                        $upd->execute([$order['id']]);
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
