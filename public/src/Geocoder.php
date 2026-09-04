<?php

class Geocoder
{
    /**
     * Яндекс HTTP Геокодер. Ключ JS API часто подходит, если геокодер разрешён для ключа.
     * @return array{lat: float, lon: float}|null
     */
    public static function geocode(string $address, string $apiKey): ?array
    {
        $address = trim($address);
        if ($address === '' || $apiKey === '') {
            return null;
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
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        $json = @file_get_contents($url, false, $ctx);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        $pos = $data['response']['GeoObjectCollection']['featureMember'][0]['GeoObject']['Point']['pos'] ?? null;
        if (!$pos) {
            return null;
        }

        // Яндекс: "lon lat"
        $parts = preg_split('/\s+/', trim($pos));
        if (count($parts) < 2) {
            return null;
        }

        return [
            'lon' => (float) $parts[0],
            'lat' => (float) $parts[1],
        ];
    }
}
