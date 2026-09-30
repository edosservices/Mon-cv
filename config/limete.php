<?php

return [

    /*
    | Durée d'essai à l'inscription. Ce n'est pas un prix.
    | Le super admin modifie les prix des plans SaaS en base.
    */
    'trial_days' => (int) env('LIMETE_TRIAL_DAYS', 14),

    'currency' => env('LIMETE_CURRENCY', 'CDF'),

    'timezone' => env('LIMETE_TIMEZONE', 'Africa/Kinshasa'),

    /*
    | Fournisseurs prévus. Seul "manual" fonctionne sans clé API.
    | Les autres restent inactifs tant que la clé .env est vide.
    */
    'payment_providers' => [
        'manual' => 'Paiement manuel / comptoir',
        'airtel_money' => 'Airtel Money',
        'orange_money' => 'Orange Money',
        'mpesa' => 'M-Pesa',
        'card' => 'Carte bancaire',
        'unipay' => 'UniPay',
    ],

    /*
    | Les clés restent vides tant qu'un contrat d'API officiel n'est pas branché.
    | Le webhook sandbox signe le corps avec le secret. Ce n'est pas le format opérateur.
    */
    'payments' => [
        'airtel_money' => [
            'api_key' => env('AIRTEL_MONEY_API_KEY'),
            'client_id' => env('AIRTEL_MONEY_CLIENT_ID'),
            'client_secret' => env('AIRTEL_MONEY_CLIENT_SECRET'),
            'webhook_secret' => env('AIRTEL_MONEY_WEBHOOK_SECRET'),
        ],
        'orange_money' => [
            'api_key' => env('ORANGE_MONEY_API_KEY'),
            'client_id' => env('ORANGE_MONEY_CLIENT_ID'),
            'client_secret' => env('ORANGE_MONEY_CLIENT_SECRET'),
            'webhook_secret' => env('ORANGE_MONEY_WEBHOOK_SECRET'),
        ],
        'mpesa' => [
            'api_key' => env('MPESA_API_KEY'),
            'client_id' => env('MPESA_CLIENT_ID'),
            'client_secret' => env('MPESA_CLIENT_SECRET'),
            'webhook_secret' => env('MPESA_WEBHOOK_SECRET'),
        ],
        'card' => [
            'secret' => env('CARD_GATEWAY_SECRET'),
            'webhook_secret' => env('CARD_GATEWAY_WEBHOOK_SECRET'),
        ],
    ],

    /*
    | Liens publics. Vides tant qu’un compte officiel n’est pas publié.
    | Aucun compte n’est inventé.
    */
    'social' => [
        'facebook' => env('LIMETE_SOCIAL_FACEBOOK'),
        'instagram' => env('LIMETE_SOCIAL_INSTAGRAM'),
        'whatsapp' => env('LIMETE_SOCIAL_WHATSAPP'),
        'tiktok' => env('LIMETE_SOCIAL_TIKTOK'),
        'youtube' => env('LIMETE_SOCIAL_YOUTUBE'),
    ],

    'edos_url' => env('LIMETE_EDOS_URL'),

];
