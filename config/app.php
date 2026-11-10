<?php

return [
    'name' => env('APP_NAME', 'GoTry'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    // Persist timestamps in UTC; display activity schedules in Australia/Sydney.
    'timezone' => 'UTC',
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_AU'),
    'search_indexing_enabled' => (bool) env('SEARCH_INDEXING_ENABLED', false),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'maintenance' => ['driver' => env('APP_MAINTENANCE_DRIVER', 'file')],
];

