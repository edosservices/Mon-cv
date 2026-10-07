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
    | Disponibilité par pays. La documentation H2H ne donne pas la liste
    | des opérateurs de la RDC. N'ajoutez un pays que lorsque iKeePay a
    | confirmé les opérateurs réellement disponibles pour ce pays.
    |
    | Exemple :
    | 'CI' => ['ORANGE', 'MTN', 'WAVE', 'MOOV'],
    */
    'countries' => [],

];
