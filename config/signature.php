<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document Signing Certificate
    |--------------------------------------------------------------------------
    |
    | Path to certificate files for signing PDF documents.
    | Use self-signed for free, or CA-issued for legal compliance.
    |
    */
    'certificate' => [
        'cert_path' => env('DOCUMENT_SIGNING_CERT_PATH', storage_path('certificates/document_signing.crt')),
        'key_path' => env('DOCUMENT_SIGNING_KEY_PATH', storage_path('certificates/document_signing.key')),
        'p12_path' => env('DOCUMENT_SIGNING_P12_PATH', storage_path('certificates/document_signing.p12')),
        'password' => env('DOCUMENT_SIGNING_PASSWORD', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature Appearance
    |--------------------------------------------------------------------------
    |
    | Default appearance settings for signature in PDF.
    |
    */
    'appearance' => [
        'name' => env('SIGNATURE_NAME', 'HyperVerge KYC'),
        'reason' => 'Identity Verification Completed',
        'location' => env('SIGNATURE_LOCATION', 'Philippines'),
        'contact' => env('SIGNATURE_CONTACT', 'admin@hyperverge.test'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature Position
    |--------------------------------------------------------------------------
    |
    | Where to place the signature appearance on the PDF.
    | Coordinates are in millimeters from top-left corner.
    |
    */
    'position' => [
        'page' => -1, // -1 for last page, 1 for first page, etc.
        'x' => 15, // millimeters from left
        'y' => 15, // millimeters from top
        'width' => 60, // millimeters
        'height' => 20, // millimeters
    ],
];
