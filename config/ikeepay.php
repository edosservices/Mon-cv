<?php

return [

    /*
    | Opérateurs cités par la documentation H2H iKeePay.
    | La liste est extensible, mais un code absent d'ici n'est jamais envoyé.
    */
    'operators' => [
        'ORANGE' => 'Orange',
        'MTN' => 'MTN',
        'WAVE' => 'Wave',
        'MOOV' => 'Moov',
        'MOBICASH' => 'Mobicash',
        'AIRTEL' => 'Airtel',
        'VODACOM' => 'Vodacom',
    ],

    /*
    | Disponibilité par pays. Le contrat publié montre un exemple CI + ORANGE
    | et cite d'autres codes (MTN, WAVE, MOOV, MOBICASH, AIRTEL, VODACOM)
    | sans dire quel pays les autorise. La RDC n'y figure pas.
    | N'ajoutez un pays que lorsque iKeePay confirme ses opérateurs.
    */
    'countries' => [],

];
