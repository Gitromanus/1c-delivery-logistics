<?php

class Geocoder
{
    public static function geocode(string $address, string $yandexKey = ''): ?array
    {
        $meta = self::geocodeWithMeta($address, $yandexKey);
        return $meta['point'] ?? null;
    }

    /**
     * Сначала Яндекс (если ключ есть), при ошибке — Nominatim (OSM).
     * @return array{point: ?array, error: ?string, provider: ?string}
     */
    public static function geocodeWithMeta(string $address, string $yandexKey = ''): array
    {
        $address = trim($address);
        if ($address === '') {
            return ['point' => null, 'error' => 'empty address', 'provider' => null];
        }

        // Уточняем регион для посёлков
        $query = $address;
        if (!preg_match('/ростов|новочерк|шахт/ui', $query)) {
            $query .= ', Ростовская область, Россия';
        }

        if ($yandexKey !== '') {
            $y = self::yandex($query, $yandexKey);
            if ($y['point']) {
                return $y + ['provider' => 'yandex'];
            }
            // fall through to OSM
            $yandexError = $y['error'];
        } else {
            $yandexError = null;
        }

        $osm = self::nominatim($query);
        if ($osm['point']) {
            return $osm + ['provider' => 'nominatim'];
        }

        return [
            'point' => null,
            'error' => trim(($yandexError ? "yandex: $yandexError; " : '') . 'osm: ' . ($osm['error'] ?? 'fail')),
            'provider' => null,
        ];
    }

    private static function yandex(string $address, string $apiKey): array
    {
        $url = 'https://geocode-maps.yandex.ru/1.x/?' . http_build_query([
            'apikey' => $apiKey,
            'geocode' => $address,
            'format' => 'json',
            'results' => 1,
            'lang' => 'ru_RU',
        ]);

        $json = self::httpGet($url);
        if ($json === null) {
            return ['point' => null, 'error' => 'network'];
        }
        $data = json_decode($json, true);
        if (isset($data['message'])) {
            return ['point' => null, 'error' => (string) $data['message']];
        }
        $pos = $data['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['Point']['pos'] ?? null;
        if (!$pos) {
            return ['point' => null, 'error' => 'no results'];
        }
        $parts = preg_split('/\s+/', trim($pos));
        return [
            'point' => ['lon' => (float) $parts[0], 'lat' => (float) $parts[1]],
            'error' => null,
        ];
    }

    private static function nominatim(string $address): array
    {
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $address,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'ru',
        ]);

        $json = self::httpGet($url, [
            'User-Agent: 1c-delivery-logistics/1.0 (Beget; logistics desk)',
            'Accept-Language: ru',
        ]);
        if ($json === null) {
            return ['point' => null, 'error' => 'network'];
        }
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data[0]['lat'], $data[0]['lon'])) {
            return ['point' => null, 'error' => 'no results'];
        }
        return [
            'point' => [
                'lat' => (float) $data[0]['lat'],
                'lon' => (float) $data[0]['lon'],
            ],
            'error' => null,
        ];
    }

    private static function httpGet(string $url, array $headers = []): ?string
    {
        $headerLines = '';
        foreach ($headers as $k => $v) {
            if (is_int($k)) {
                $headerLines .= $v . "\r\n";
            } else {
                $headerLines .= $k . ': ' . $v . "\r\n";
            }
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 12,
                'ignore_errors' => true,
                'header' => $headerLines,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }
}
