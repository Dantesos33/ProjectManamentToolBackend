<?php

return [
    'access_ttl_minutes' => (int) env('ACCESS_TOKEN_TTL', 15),
    'ttl_days'           => (int) env('REFRESH_TOKEN_TTL_DAYS', 7),
    'grace_seconds'      => 10,   // tolerates StrictMode and multi-tab double refresh
    'cookie_domain'      => env('REFRESH_COOKIE_DOMAIN'),
    'secure'             => (bool) env('REFRESH_COOKIE_SECURE', env('APP_ENV') === 'production'),
    'same_site'          => env('REFRESH_COOKIE_SAMESITE', 'lax'),
];