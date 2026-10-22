<?php

return [
    'weather' => [
        'url' => env('WEATHER_API_URL', 'https://api.open-meteo.com/v1/forecast'),
        'key' => env('WEATHER_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'maptiler' => [
        'key' => env('MAPTILER_KEY'),
    ],

    'mypwa' => [
        'api_url' => env('MYPWA_API_URL', 'https://mypwa.ru/api/v1'),
        'token'   => env('MYPWA_API_TOKEN'),
        'timeout' => env('MYPWA_API_TIMEOUT', 30),
    ],
    'mail' => [
        'admin' => env('MAIL_ADMIN'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id'   => env('TELEGRAM_CHAT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LiteRouter — ИИ-помощник платформы
    |--------------------------------------------------------------------------
    |
    | OpenAI-совместимый API. Ключ хранится только в .env и используется
    | исключительно на сервере: во frontend, сборку и логи он не попадает.
    |
    */

    'literouter' => [
        'base_url' => env('LITEROUTER_BASE_URL', 'https://api.literouter.com/v1'),
        'key'      => env('LITEROUTER_API_KEY'),
        'model'    => env('LITEROUTER_MODEL', 'deepseek-v4-flash-cheap:free'),
        'timeout'  => (int) env('LITEROUTER_TIMEOUT', 45),
        'enabled'  => (bool) env('AI_CHAT_ENABLED', true),
        'per_minute' => (int) env('AI_CHAT_PER_MINUTE', 5),
        'per_day' => (int) env('AI_CHAT_PER_DAY', 100),
    ],
];
