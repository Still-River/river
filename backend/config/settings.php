<?php

return [
    'settings' => [
        'displayErrorDetails' => ['APP_DEBUG'] ?? false,
        'database' => [
            'driver' => 'mysql',
            'host' => ['DB_HOST'] ?? 'db',
            'database' => ['DB_DATABASE'] ?? 'river',
            'username' => ['DB_USERNAME'] ?? 'river',
            'password' => ['DB_PASSWORD'] ?? 'river_pass',
            'port' => (int) (['DB_PORT'] ?? 3306),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
];
