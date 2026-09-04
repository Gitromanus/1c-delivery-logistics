<?php
/**
 * Скопируйте в config.php и заполните.
 * config.php не должен попадать в git (см. .gitignore).
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'your_db_name',
        'user' => 'your_db_user',
        'pass' => 'your_db_password',
        'charset' => 'utf8mb4',
    ],
    // Ключ для POST /api/orders.php из 1С
    'api_key' => 'change-me-to-long-random-string',
    // Пароль простой админки /admin/
    'admin_password' => 'admin',
    'timezone' => 'Europe/Moscow',
];
