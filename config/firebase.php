<?php

return [
    'realtime' => [
        'enabled' => env('FIREBASE_REALTIME_ENABLED', false),
        'database_url' => env('FIREBASE_DATABASE_URL'),
        'web_config_path' => env('FIREBASE_WEB_CONFIG_PATH', base_path('firebaseconfig.txt')),
        'credentials_path' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase-service-account.json')),
        'root_path' => trim((string) env('FIREBASE_REALTIME_ROOT', 'notifications'), '/'),
        'request_timeout' => (int) env('FIREBASE_REALTIME_TIMEOUT', 3),
        'cleanup_time' => env('FIREBASE_REALTIME_CLEANUP_TIME', '23:59'),
        'cleanup_timezone' => env('FIREBASE_REALTIME_CLEANUP_TIMEZONE', 'America/Mexico_City'),
        'pulse_throttle_seconds' => (int) env('FIREBASE_PULSE_THROTTLE_SECONDS', 300),
    ],
];
