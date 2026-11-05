<?php

return [
    'turnstile' => [
        'enabled' => env('TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'hostnames' => array_values(array_filter(array_map('trim', explode(',',
            env('TURNSTILE_HOSTNAMES') ?: (parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: '')
        )))),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_REFRESH_TOKEN'),
        'sender' => env('GMAIL_SENDER'),
    ],
];
