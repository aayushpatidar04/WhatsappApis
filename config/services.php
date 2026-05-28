<?php

return [

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

    // Baileys Node.js Microservice
    'baileys' => [
        // 'url'    => env('BAILEYS_SERVICE_URL', 'http://127.0.0.1:3000'),
        'url'    => env('BAILEYS_SERVICE_URL', 'https://wa-api.intouchsoftwaresolution.in'),
        'secret' => env('BAILEYS_INTERNAL_SECRET', 'aayush-patidar'),
        'timeout'=> env('BAILEYS_TIMEOUT', 10),
    ],
 
    // Instance Credit System
    'credits' => [
        'grace_period_days'   => env('INSTANCE_GRACE_PERIOD_DAYS', 7),
        'expiry_warning_days' => [7, 3],
    ],
 
    // Rate Limiting Defaults
    'rate_limits' => [
        'default_per_minute' => env('DEFAULT_MESSAGES_PER_MINUTE', 20),
        'min_per_minute'     => env('MIN_MESSAGES_PER_MINUTE', 5),
        'max_per_minute'     => env('MAX_MESSAGES_PER_MINUTE', 60),
    ],

    // ── Pusher ────────────────────────────────────────────────────────────────
    'pusher' => [
        'secret'  => env('PUSHER_APP_SECRET'),
        'app_id'  => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER', 'ap2'),
            'useTLS'  => true,
        ],
    ],

    // ── Razorpay ──────────────────────────────────────────────────────────────
    'razorpay' => [
        'key_id'     => env('RAZORPAY_KEY_ID', ''),
        'key_secret' => env('RAZORPAY_KEY_SECRET', ''),
    ],
 
    // ── Stripe ────────────────────────────────────────────────────────────────
    'stripe' => [
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY', ''),
        'secret_key'      => env('STRIPE_SECRET_KEY', ''),
        'webhook_secret'  => env('STRIPE_WEBHOOK_SECRET', 'aayush-patidar'),
    ],
 
    // ── Active payment gateway ────────────────────────────────────────────────
    'payment' => [
        'default' => env('PAYMENT_GATEWAY', 'razorpay'),  // 'razorpay' | 'stripe'
    ],

];
