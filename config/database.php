<?php

return [
    'default' => $_ENV['DB_CONNECTION'] ?? 'mysql', // Default connection name

    'connections' => [
        'mysql' => [
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'dbname' => $_ENV['DB_DATABASE'] ?? 'test_api',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:', // ใช้ฐานข้อมูลในหน่วยความจำ
            'prefix' => '',
        ],
    ],
];