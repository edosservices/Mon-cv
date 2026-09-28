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
    ],

];
