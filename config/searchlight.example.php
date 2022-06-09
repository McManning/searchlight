<?php

return [
    'providers' => [
        'sims' => [
            // For AWS OpenSearch support, we need full https and port defined.
            'host' => 'https://vpc-research-portal-dev-wm5y5w2bzm2xw7ys5rn6mye2k4.us-east-2.es.amazonaws.com:443',
            'http_basic_auth' => [
                'username' => env('SIMS_HTTP_USER'),
                'password' => env('SIMS_HTTP_PASS'),
            ],
            'index' => 'demo-index-sims',
            'result_type' => 'LocationSet',
            'hit_type' => [
                // Mapping of _doc type to GraphQL type.
                // If `hit_type` value is a string, then all hits are mapped to the same type.
                'room' => 'RoomHit',
                'building' => 'BuildingHit',
            ],
        ],

        // TODO: Configs don't merge nested objects in Laravel
        // so defining a default provider will replace *all* configurations of the default.
        // Either deep merge or just have instructions on replacing the provider as a whole.
        // (Probably the latter)

        // 'default' => [
        //     'host' => 'vpc-research-portal-dev-wm5y5w2bzm2xw7ys5rn6mye2k4.us-east-2.es.amazonaws.com',
        //     'index' => 'demo-index-catalog',

        //     'hit_type' => 'ResultHit',
        // ],

    ],
];

