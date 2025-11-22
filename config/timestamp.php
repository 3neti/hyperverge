<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Blockchain Timestamping
    |--------------------------------------------------------------------------
    |
    | Configuration for blockchain timestamp services.
    |
    */
    'service' => env('TIMESTAMP_SERVICE', 'opentimestamps'),

    'opentimestamps' => [
        'enabled' => env('TIMESTAMP_ENABLED', true),
        'calendar_url' => env('OPENTIMESTAMPS_URL', 'https://alice.btc.calendar.opentimestamps.org'),
        'timeout' => env('TIMESTAMP_TIMEOUT', 10), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Where to store timestamp proofs.
    |
    */
    'storage' => [
        'disk' => env('TIMESTAMP_STORAGE_DISK', 'local'),
        'path' => 'timestamps',
    ],
];
