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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'h2h' => [
        'base_url' => env('H2H_BASE_URL', 'https://h2h.okeconnect.com'),
        'member_id' => env('H2H_MEMBER_ID'),
        'pin' => env('H2H_PIN'),
        'password' => env('H2H_PASSWORD'),
    ],

    'okeconnect' => [
        'price_url' => env('OKECONNECT_PRICE_URL', 'https://okeconnect.com/harga/json'),
        'price_id' => env('OKECONNECT_PRICE_ID'),
        'price_products' => env('OKECONNECT_PRICE_PRODUCTS', 'saldo_gojek,digital'),
    ],

    'qris' => [
        'code' => env('QRIS_CODE'),
    ],

];
