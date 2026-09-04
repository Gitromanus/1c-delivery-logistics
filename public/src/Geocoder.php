<?php

class Geocoder
{
    public static function geocode(string $address, string $yandexKey = ''): ?array
    {
        $meta = self::geocodeWithMeta($address, $yandexKey);
        return $meta['point'] ?? null;
    }

    public static function geocodeWithMeta(string $address, string $yandexKey = ''): array
    {
        $address = trim($address);
        if ($address === '') {
            return ['point' => null, 'error' => 'empty address', 'provider' => null];
        }

        if ($yandexKey !== '') {
            $y = self::yandex($address . ', Россия', $yandexKey);
            if ($y['point']) {
                return ['point' => $y['point'], 'error' => null, 'provider' => 'yandex'];
            }
            $yandexError = $y['error'];
        } else {
            $yandexError = null;
        }

        foreach (self::queryVariants($address) as $q) {
            $osm = self::nominatim($q);
            if ($osm['point']) {
                return ['point' => $osm['point'], 'error' => null, 'provider' => 'nominatim'];
            }
            usleep(400000);
        }

        return [
            'point' => null,
            'error' => trim(($yandexError ? "yandex: $yandexError; " : '') . 'osm: no results'),
            'provider' => null,
        ];
    }

    /** Несколько формулировок — посёлки OSM знает хуже, чем города */
    private static function queryVariants(string $address): array
    {
        $a = preg_replace('/\s+/u', ' ', trim($address));
        $variants = [$a];

        // Без «пос.» / «п.»
        $variants[] = preg_replace('/\b(пос\.?|п\.|посёлок|поселок)\s+/ui', '', $a);

        // Только населённый пункт + область
        if (preg_match('/Молод[её]жн\w*/ui', $a, $m)) {
            $variants[] = 'посёлок Молодёжный Октябрьский район Ростовская область';
            $variants[] = 'Молодёжный Ростовская область';
        }
        if (preg_match('/Донск\w*/ui', $a, $m)) {
            $variants[] = 'посёлок Донской Октябрьский район Ростовская область';
            $variants[] = 'Донской Ростовская область';
        }
        if (preg_match('/Новочеркасск/ui', $a)) {
            $variants[] = $a . ', Ростовская область';
            // улица + город
            if (preg_match('/(ул\.?|улица|пр\.?|проспект|пер\.?|переулок)\s*([^,]+)/ui', $a, $um)) {
                $variants[] = trim($um[0]) . ', Новочеркасск, Ростовская область';
            }
        }
        if (preg_match('/Шахт/ui', $a)) {
            $variants[] = $a . ', Ростовская область';
        }

        $variants[] = $a . ', Ростовская область, Россия';

        // уникальные
        $out = [];
        foreach ($variants as $v) {
            $v = trim(preg_replace('/\s+/u', ' ', $v));
            if ($v !== '' && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        }
        return $out;
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
            'addressdetails' => 0,
        ]);

        $json = self::httpGet($url, [
            'User-Agent: 1c-delivery-logistics/1.0 (contact: logistics-desk)',
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
            $headerLines .= (is_int($k) ? $v : ($k . ': ' . $v)) . "\r\n";
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => $headerLines,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }
}
