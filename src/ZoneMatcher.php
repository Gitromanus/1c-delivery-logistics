<?php

class ZoneMatcher
{
    /**
     * Подбор зоны по подстрокам keywords в адресе (пока без геокодера).
     */
    public static function matchZoneId(PDO $pdo, string $address): ?int
    {
        $addressLower = mb_strtolower($address, 'UTF-8');
        $zones = $pdo->query('SELECT id, keywords FROM zones WHERE is_active = 1')->fetchAll();

        foreach ($zones as $zone) {
            if (empty($zone['keywords'])) {
                continue;
            }
            $parts = preg_split('/\s*;\s*/u', $zone['keywords']);
            foreach ($parts as $part) {
                $part = trim(mb_strtolower($part, 'UTF-8'));
                if ($part !== '' && mb_strpos($addressLower, $part) !== false) {
                    return (int) $zone['id'];
                }
            }
        }
        return null;
    }
}
