<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'your_db_name',
        'user' => 'your_db_user',
        'pass' => 'your_db_password',
        'charset' => 'utf8mb4',
    ],
    // Ключ для POST /api/orders.php из 1С (свой секрет, НЕ ключ Яндекса)
    'api_key' => 'change-me-to-long-random-string',
    // Ключ JavaScript API Яндекс.Карт
    'yandex_maps_key' => '',
    'admin_password' => 'admin',
    'timezone' => 'Europe/Moscow',
];
