<?php

return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'gmail_api' => [
            'transport' => 'gmail_api',
        ],
        'resend' => [
            'transport' => 'resend',
        ],
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
        'array' => [
            'transport' => 'array',
        ],
    ],
    'from' => [
        'address' => env('GMAIL_SENDER', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'name' => env('MAIL_FROM_NAME', 'UniOrg'),
    ],
];
