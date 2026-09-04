<?php

class Geocoder
{
    /**
     * @return array{lat: float, lon: float}|null
     */
    public static function geocode(string $address, string $apiKey): ?array
    {
        $result = self::geocodeWithMeta($address, $apiKey);
        return $result['point'] ?? null;
    }

    /**
     * @return array{point: ?array, error: ?string, raw_status: ?string}
     */
    public static function geocodeWithMeta(string $address, string $apiKey): array
    {
        $address = trim($address);
        if ($address === '' || $apiKey === '') {
            return ['point' => null, 'error' => 'empty address or key', 'raw_status' => null];
        }

        $url = 'https://geocode-maps.yandex.ru/1.x/?' . http_build_query([
            'apikey' => $apiKey,
            'geocode' => $address,
            'format' => 'json',
            'results' => 1,
            'lang' => 'ru_RU',
        ]);

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $json = @file_get_contents($url, false, $ctx);
        if ($json === false) {
            return ['point' => null, 'error' => 'network error', 'raw_status' => null];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['point' => null, 'error' => 'invalid json: ' . mb_substr($json, 0, 200), 'raw_status' => null];
        }

        if (isset($data['message'])) {
            return ['point' => null, 'error' => (string) $data['message'], 'raw_status' => $data['status'] ?? null];
        }

        $members = $data['response']['GeoObjectCollection']['featureMember'] ?? [];
        if (!$members) {
            return ['point' => null, 'error' => 'no results', 'raw_status' => null];
        }

        $pos = $members[0]['GeoObject']['Point']['pos'] ?? null;
        if (!$pos) {
            return ['point' => null, 'error' => 'no point', 'raw_status' => null];
        }

        $parts = preg_split('/\s+/', trim($pos));
        return [
            'point' => [
                'lon' => (float) $parts[0],
                'lat' => (float) $parts[1],
            ],
            'error' => null,
            'raw_status' => null,
        ];
    }
}
