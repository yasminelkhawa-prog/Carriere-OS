<?php

return [
    'required' => [
        'APP_URL' => env('APP_URL'),
        'QUEUE_CONNECTION' => env('QUEUE_CONNECTION'),
        'FILESYSTEM_DISK' => env('FILESYSTEM_DISK'),
        'GEMINI_API_KEY' => env('GEMINI_API_KEY'),
        'DB_CONNECTION' => env('DB_CONNECTION'),
        'DB_HOST' => env('DB_HOST'),
        'DB_PORT' => env('DB_PORT'),
        'DB_DATABASE' => env('DB_DATABASE'),
        'DB_USERNAME' => env('DB_USERNAME'),
        'DB_PASSWORD' => env('DB_PASSWORD'),
        'MAIL_MAILER' => env('MAIL_MAILER'),
        'MAIL_HOST' => env('MAIL_HOST'),
        'MAIL_PORT' => env('MAIL_PORT'),
        'MAIL_USERNAME' => env('MAIL_USERNAME'),
        'MAIL_PASSWORD' => env('MAIL_PASSWORD'),
        'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
    ],
    'allow_empty_in_local' => [
        'DB_PASSWORD',
    ],

    'admin_ips' => array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('SETUP_ADMIN_IPS', '127.0.0.1,::1'))
    )),
];
