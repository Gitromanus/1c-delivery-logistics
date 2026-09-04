<?php

class Geocoder
{
    // Зона доставки по загруженным заявкам: Ростовская область + ДНР + ЛНР.
    // Юго-запад: lat 46–49, lon 36.8–41.5. Позволяет отсечь одноимённые
    // посёлки (Шахты, Горный, Свердловск и т.п.) на Урале/в Сибири/на Дальнем Востоке.
    private const LAT_MIN = 46.0;
    private const LAT_MAX = 49.0;
    private const LON_MIN = 36.8;
    private const LON_MAX = 41.5;

    public static function geocode(string $address, string $yandexKey = '', string $dadataToken = ''): ?array
    {
        $meta = self::geocodeWithMeta($address, $yandexKey, $dadataToken);
        return $meta['point'] ?? null;
    }

    public static function geocodeWithMeta(string $address, string $yandexKey = '', string $dadataToken = ''): array
    {
        $address = trim($address);
        if ($address === '') {
            return ['point' => null, 'error' => 'empty address', 'provider' => null];
        }

        // Dadata — лучшая точность по РФ (бесплатно до ~10k/сутки)
        if ($dadataToken !== '') {
            $dd = self::dadata($address, $dadataToken);
            if ($dd['point']) {
                return ['point' => $dd['point'], 'error' => null, 'provider' => 'dadata'];
            }
        }

        if ($yandexKey !== '') {
            $isDnrLnr = (bool) preg_match('/Народная Республика|Народная респ|ДНР|ЛНР/iu', $address);
            $y = self::yandex($isDnrLnr ? $address : $address . ', Ростовская область, Россия', $yandexKey);
            if ($y['point'] && self::inRegion($y['point']['lat'], $y['point']['lon'])) {
                return ['point' => $y['point'], 'error' => null, 'provider' => 'yandex'];
            }
            $yandexError = $y['error'] ?? 'out of region';
        } else {
            $yandexError = null;
        }

        // Photon (OSM-based, бесплатно, без ключа)
        $ph = self::photon($address);
        if ($ph['point']) {
            return ['point' => $ph['point'], 'error' => null, 'provider' => 'photon'];
        }

        foreach (self::queryVariants($address) as $q) {
            $osm = self::nominatim($q);
            if ($osm['point'] && self::inRegion($osm['point']['lat'], $osm['point']['lon'])) {
                return ['point' => $osm['point'], 'error' => null, 'provider' => 'nominatim'];
            }
            usleep(350000);
        }

        // Запасные «центры» известных зон из тестовых адресов (лучше далеко не ставить)
        $fallback = self::zoneFallback($address);
        if ($fallback) {
            return ['point' => $fallback, 'error' => null, 'provider' => 'fallback_zone'];
        }

        return [
            'point' => null,
            'error' => trim(($yandexError ? "yandex: $yandexError; " : '') . 'osm: no results in region'),
            'provider' => null,
        ];
    }

    public static function inRegion(float $lat, float $lon): bool
    {
        return $lat >= self::LAT_MIN && $lat <= self::LAT_MAX
            && $lon >= self::LON_MIN && $lon <= self::LON_MAX;
    }

    private static function zoneFallback(string $address): ?array
    {
        // Центры населённых пунктов зоны доставки (приблизительно).
        // Используются, когда OSM/Яндекс не нашли точную улицу —
        // заявка ставится хотя бы в центр своего города/посёлка.
        $map = [
            // Ростовская область — крупные города
            'молод' => ['lat' => 47.5185, 'lon' => 40.0975],
            'донск' => ['lat' => 47.4250, 'lon' => 40.0450],
            'новочеркас' => ['lat' => 47.4110, 'lon' => 40.0910],
            'шахт' => ['lat' => 47.7085, 'lon' => 40.2150],
            // Ростовская область — мелкие н.п., слабо проиндексированные в OSM
            'горн' => ['lat' => 47.8050, 'lon' => 40.1250],          // рп Горный / Горненское (Красносулинский р-н)
            'каменоломни' => ['lat' => 47.6650, 'lon' => 40.2130],   // рп Каменоломни
            'заплавск' => ['lat' => 47.3470, 'lon' => 40.0280],      // ст-ца Заплавская
            'бессергеневск' => ['lat' => 47.2920, 'lon' => 40.3830], // ст-ца Бессергеневская
            'красюковск' => ['lat' => 47.5050, 'lon' => 40.3150],    // сл Красюковская / Красюковское
            'яново-грушевский' => ['lat' => 47.4400, 'lon' => 40.1900], // х Яново-Грушевский
            'грушевск' => ['lat' => 47.4150, 'lon' => 40.0100],      // ст-ца Грушевская
            'таганрог' => ['lat' => 47.2363, 'lon' => 38.8969],      // г. Таганрог
            'киреевк' => ['lat' => 47.3300, 'lon' => 40.1700],       // х. Киреевка (Октябрьский р-н)
            // Октябрьский район (мелкие н.п., часто не находящиеся в OSM)
            'персиановск' => ['lat' => 47.5200, 'lon' => 40.0900],   // п Персиановский / Персиановское
            'кривянск' => ['lat' => 47.5500, 'lon' => 40.1700],      // ст-ца Кривянская / Кривянское
            'казачьи лагери' => ['lat' => 47.4900, 'lon' => 40.1600], // п Казачьи Лагери
            'реконструктор' => ['lat' => 47.3800, 'lon' => 39.8700], // п Реконструктор (Большелогское)
            // ДНР
            'торез' => ['lat' => 48.0170, 'lon' => 38.5610],
            'енаки' => ['lat' => 48.2310, 'lon' => 38.2120],
            'снежн' => ['lat' => 48.0230, 'lon' => 38.7700],
            'жданов' => ['lat' => 48.1550, 'lon' => 38.2580],
            // ЛНР
            'антрацит' => ['lat' => 48.1190, 'lon' => 39.0910],
            'красный луч' => ['lat' => 48.1340, 'lon' => 38.9320],
            'свердловск' => ['lat' => 48.0790, 'lon' => 39.6510],
            'г. луганск' => ['lat' => 48.5690, 'lon' => 39.3070],
        ];
        $lower = mb_strtolower($address, 'UTF-8');
        foreach ($map as $needle => $point) {
            if (mb_strpos($lower, $needle) !== false) {
                return $point;
            }
        }
        return null;
    }

    private static function queryVariants(string $address): array
    {
        $a = preg_replace('/\s+/u', ' ', trim($address));
        $variants = [];

        if (preg_match('/Молод[её]жн\w*/ui', $a)) {
            $variants[] = 'Молодёжный, Октябрьский район, Ростовская область, Россия';
            $variants[] = 'poselok Molodezhny, Rostov Oblast';
        }
        if (preg_match('/Донск\w*/ui', $a)) {
            $variants[] = 'Донской, Октябрьский район, Ростовская область, Россия';
        }
        if (preg_match('/Новочеркасск/ui', $a)) {
            $variants[] = $a . ', Ростовская область, Россия';
            if (preg_match('/(ул\.?|улица|пр\.?|проспект|пер\.?|переулок)\s*([^,]+)/ui', $a, $um)) {
                $variants[] = trim($um[0]) . ', Новочеркасск, Ростовская область, Россия';
            }
        }
        if (preg_match('/Шахт/ui', $a)) {
            $variants[] = $a . ', Ростовская область, Россия';
        }

        // Для адресов ДНР/ЛНР не приписываем «Ростовская область», иначе
        // Nominatim ищет их в неверном регионе и не находит.
        if (preg_match('/Народная Республика|Народная респ|ДНР|ЛНР/iu', $a)) {
            $variants[] = $a;
        } else {
            $variants[] = $a . ', Ростовская область, Россия';
            $variants[] = $a;
        }

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
            'bbox' => sprintf('%s,%s~%s,%s', self::LON_MIN, self::LAT_MIN, self::LON_MAX, self::LAT_MAX),
            'rspn' => 1,
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
        // viewbox: left, top, right, bottom (lon/lat)
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $address,
            'format' => 'json',
            'limit' => 3,
            'countrycodes' => 'ru,ua',
            'viewbox' => sprintf('%s,%s,%s,%s', self::LON_MIN, self::LAT_MAX, self::LON_MAX, self::LAT_MIN),
            'bounded' => 1,
        ]);

        $json = self::httpGet($url, [
            'User-Agent: 1c-delivery-logistics/1.0 (contact: logistics-desk)',
            'Accept-Language: ru',
        ]);
        if ($json === null) {
            return ['point' => null, 'error' => 'network'];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['point' => null, 'error' => 'no results'];
        }
        foreach ($data as $row) {
            if (!isset($row['lat'], $row['lon'])) {
                continue;
            }
            $lat = (float) $row['lat'];
            $lon = (float) $row['lon'];
            if (self::inRegion($lat, $lon)) {
                return ['point' => ['lat' => $lat, 'lon' => $lon], 'error' => null];
            }
        }
        return ['point' => null, 'error' => 'no results'];
    }

    private static function dadata(string $address, string $token): array
    {
        if ($token === '') {
            return ['point' => null, 'error' => 'no token'];
        }
        $url = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address';
        $json = self::httpPostJson($url, json_encode([
            'query' => $address,
            'count' => 5,
        ], JSON_UNESCAPED_UNICODE), [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Token ' . $token,
        ]);
        if ($json === null) {
            return ['point' => null, 'error' => 'network'];
        }
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['suggestions'])) {
            return ['point' => null, 'error' => 'no results'];
        }
        foreach ($data['suggestions'] as $s) {
            $d = $s['data'] ?? [];
            if (!isset($d['geo_lat'], $d['geo_lon'])) {
                continue;
            }
            $lat = (float) $d['geo_lat'];
            $lon = (float) $d['geo_lon'];
            if (self::inRegion($lat, $lon)) {
                return ['point' => ['lat' => $lat, 'lon' => $lon], 'error' => null];
            }
        }
        return ['point' => null, 'error' => 'no results in region'];
    }

    private static function photon(string $address): array
    {
        // Photon (komoot) — OSM, бесплатно, без ключа. bbox ограничивает зону доставки.
        $url = 'https://photon.komoot.io/api/?' . http_build_query([
            'q' => $address,
            'limit' => 5,
            'lang' => 'ru',
            'bbox' => sprintf('%s,%s,%s,%s', self::LON_MIN, self::LAT_MIN, self::LON_MAX, self::LAT_MAX),
        ]);

        $json = self::httpGet($url, ['User-Agent: 1c-delivery-logistics/1.0 (contact: logistics-desk)']);
        if ($json === null) {
            return ['point' => null, 'error' => 'network'];
        }
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['features'])) {
            return ['point' => null, 'error' => 'no results'];
        }
        foreach ($data['features'] as $f) {
            $geom = $f['geometry']['coordinates'] ?? null; // [lon, lat]
            if (!is_array($geom) || count($geom) < 2) {
                continue;
            }
            $lon = (float) $geom[0];
            $lat = (float) $geom[1];
            if (self::inRegion($lat, $lon)) {
                return ['point' => ['lat' => $lat, 'lon' => $lon], 'error' => null];
            }
        }
        return ['point' => null, 'error' => 'no results in region'];
    }

    private static function httpPostJson(string $url, string $body, array $headers = []): ?string
    {
        $headerLines = '';
        foreach ($headers as $h) {
            $headerLines .= $h . "\r\n";
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => $headerLines . 'Content-Length: ' . strlen($body) . "\r\n",
                'content' => $body,
            ],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return $res === false ? null : $res;
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
