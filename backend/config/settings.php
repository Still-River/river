<?php

declare(strict_types=1);

$env = array_merge($_ENV, $_SERVER);

return [
    'settings' => [
        'displayErrorDetails' => filter_var($env['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
        'app_url' => $env['APP_URL'] ?? 'http://localhost:8080',
        'frontend_app_url' => $env['FRONTEND_APP_URL'] ?? 'http://localhost:5173',
        'database' => [
            'driver' => 'mysql',
            'host' => $env['DB_HOST'] ?? 'db',
            'database' => $env['DB_DATABASE'] ?? 'river',
            'username' => $env['DB_USERNAME'] ?? 'river',
            'password' => $env['DB_PASSWORD'] ?? 'river_pass',
            'port' => (int) ($env['DB_PORT'] ?? 3306),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        'google_oauth' => [
            'client_id' => $env['GOOGLE_CLIENT_ID'] ?? '',
            'client_secret' => $env['GOOGLE_CLIENT_SECRET'] ?? '',
            'redirect_uri' => $env['GOOGLE_REDIRECT_URI']
                ?? sprintf('%s/auth/google/callback', $env['APP_URL'] ?? 'http://localhost:8080'),
            'scopes' => array_values(array_filter(array_map('trim', explode(' ', $env['GOOGLE_SCOPES'] ?? 'openid email profile')))),
            'prompt' => $env['GOOGLE_PROMPT'] ?? 'consent',
            'access_type' => $env['GOOGLE_ACCESS_TYPE'] ?? 'offline',
        ],
        'cors' => [
            'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', $env['CORS_ALLOWED_ORIGINS'] ?? ($env['FRONTEND_APP_URL'] ?? 'http://localhost:5173'))))),
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
            'allow_credentials' => true,
        ],
        'session' => [
            'name' => $env['SESSION_NAME'] ?? 'river_session',
            'lifetime' => (int) ($env['SESSION_LIFETIME'] ?? 1209600),
            'domain' => $env['SESSION_DOMAIN'] ?? '',
            'secure' => filter_var($env['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOL),
            'same_site' => $env['SESSION_SAME_SITE'] ?? 'Lax',
        ],
    ],
];
