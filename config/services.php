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

    // API externa simulada con interruptor de caída (docker/fake-crm), solo para la demo de resiliencia.
    'fake_crm' => [
        'url' => env('FAKE_CRM_URL', 'http://127.0.0.1:8080'),
    ],

    // Clasificación de leads con IA. Sin API key, los leads van directo a revisión humana.
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 20),
        'requests_per_minute' => (int) env('GEMINI_REQUESTS_PER_MINUTE', 12),
    ],

    'classification' => [
        // Por debajo de esta confianza decide una persona.
        'min_confidence' => (float) env('CLASSIFICATION_MIN_CONFIDENCE', 0.7),
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
