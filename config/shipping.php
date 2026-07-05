<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Shipping Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default shipping driver that will be used when
    | no specific driver is requested. You may change this to any of the
    | drivers defined in the "drivers" array below.
    |
    */

    'default' => env('SHIPPING_DRIVER', 'shipstation'),

    /*
    |--------------------------------------------------------------------------
    | Shipping Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many shipping drivers as you wish, and you
    | may even configure multiple instances of the same driver. Defaults have
    | been set up for each supported driver.
    |
    */

    'drivers' => [

        'shipstation' => [
            'driver'   => 'shipstation',
            'api_key'  => env('SHIPSTATION_API_KEY'),
            'sandbox'  => env('SHIPSTATION_SANDBOX', true),
            'base_url' => env('SHIPSTATION_BASE_URL', 'https://api.shipstation.com'),
        ],

        'shippo' => [
            'driver'  => 'shippo',
            'api_key' => env('SHIPPO_API_KEY'),
            'sandbox' => env('SHIPPO_SANDBOX', true),
            'base_url' => 'https://api.goshippo.com',
        ],

        'easypost' => [
            'driver'  => 'easypost',
            'api_key' => env('EASYPOST_API_KEY'),
            'sandbox' => env('EASYPOST_SANDBOX', true),
            'base_url' => 'https://api.easypost.com',
        ],

    ],

];
