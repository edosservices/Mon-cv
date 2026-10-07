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

    /*
    | UniPay Congo. Les valeurs viennent de .env. Le mode live n'est jamais
    | choisi par défaut : seule la valeur exacte "live" l'active.
    */
    'unipay' => [
        'key' => env('UNIPAY_API_KEY'),
        'base_url' => env('UNIPAY_BASE_URL'),
        'webhook_secret' => env('UNIPAY_WEBHOOK_SECRET'),
        'mode' => env('UNIPAY_MODE', 'test'),
    ],

    /*
    | iKeePay. La Secret Key reste dans .env et ne sort jamais vers le navigateur.
    | Le checkout inline et le H2H Payin sont deux parcours séparés.
    */
    'ikeepay' => [
        'public_key' => env('IKPAY_PUBLIC_KEY'),
        'secret_key' => env('IKPAY_SECRET_KEY'),
        'base_url' => env('IKPAY_BASE_URL', 'https://api.ikeepay.com'),
        'checkout_url' => env('IKPAY_CHECKOUT_URL', 'https://www.ikeepay.com/checkout/v1/inline'),
    ],

];
