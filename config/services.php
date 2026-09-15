<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'api' => [
        'requests_per_minute' => (int) env('API_REQUESTS_PER_MINUTE', 60),
    ],

    // Entrega de los eventos del outbox a un sistema externo.
    'delivery' => [
        'webhook_url' => env('DELIVERY_WEBHOOK_URL'),
        'timeout_seconds' => (int) env('DELIVERY_TIMEOUT_SECONDS', 5),
        'failure_threshold' => (int) env('DELIVERY_FAILURE_THRESHOLD', 5),
        'cooldown_seconds' => (int) env('DELIVERY_COOLDOWN_SECONDS', 30),
        'base_delay_seconds' => (int) env('DELIVERY_BASE_DELAY_SECONDS', 2),
        'max_delay_seconds' => (int) env('DELIVERY_MAX_DELAY_SECONDS', 300),
        'give_up_after_seconds' => (int) env('DELIVERY_GIVE_UP_AFTER_SECONDS', 86400),
    ],

    'n8n' => [
        'base_url' => env('N8N_BASE_URL', 'http://127.0.0.1:5678'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
