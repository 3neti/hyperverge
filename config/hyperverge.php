<?php

return [
    /**
     * HyperVerge API Base URL
     * Regional endpoints: ind.idv (India), sgp.idv (Singapore)
     */
    'base_url' => env('HYPERVERGE_BASE_URL', 'https://ind.idv.hyperverge.co/v1'),

    /**
     * HyperVerge App ID (from your dashboard)
     */
    'app_id' => env('HYPERVERGE_APP_ID'),

    /**
     * HyperVerge App Key (from your dashboard)
     */
    'app_key' => env('HYPERVERGE_APP_KEY'),

    /**
     * Default workflow ID for Link KYC
     * This must be configured in your HyperVerge dashboard
     */
    'url_workflow' => env('HYPERVERGE_URL_WORKFLOW', 'onboarding'),

    /**
     * HTTP request timeout in seconds
     */
    'timeout' => env('HYPERVERGE_TIMEOUT', 30),

    /**
     * Test mode - bypasses actual API calls for development
     */
    'test_mode' => env('HYPERVERGE_TEST_MODE', false),

    /**
     * Webhook configuration
     */
    'webhook' => [
        'secret' => env('HYPERVERGE_WEBHOOK_SECRET'),
        'timeout' => 30,
        'verify_signature' => env('HYPERVERGE_VERIFY_WEBHOOK', true),
    ],

    /**
     * KYC validation rules
     */
    'validation' => [
        // Minimum face match score (0.0 - 1.0)
        'min_face_match_score' => env('HYPERVERGE_MIN_FACE_MATCH', 0),
        
        // Minimum liveness score (0.0 - 1.0) - null to skip
        'min_liveness_score' => env('HYPERVERGE_MIN_LIVENESS', null),
        
        // Require liveness check to pass
        'require_liveness' => env('HYPERVERGE_REQUIRE_LIVENESS', false),
        
        // Application statuses that are considered approved
        'allowed_statuses' => ['approved', 'needs_review', 'auto_approved'],
        
        // Application statuses that are auto-rejected
        'rejected_statuses' => ['rejected', 'auto_declined'],
    ],

    /**
     * Storage settings for KYC images
     */
    'storage' => [
        'disk' => env('HYPERVERGE_STORAGE_DISK', 'public'),
        'path' => 'kyc',
    ],

    /**
     * Image download settings
     */
    'images' => [
        'timeout' => env('HYPERVERGE_IMAGE_TIMEOUT', 30),
        'max_retries' => env('HYPERVERGE_IMAGE_RETRIES', 3),
        'verify_ssl' => env('HYPERVERGE_VERIFY_SSL', true),
    ],

    /**
     * Webhook settings
     */
    'webhook' => [
        'secret' => env('HYPERVERGE_WEBHOOK_SECRET'),
        'model_class' => env('HYPERVERGE_WEBHOOK_MODEL_CLASS', \App\Models\User::class),
        'transaction_id_field' => env('HYPERVERGE_WEBHOOK_TRANSACTION_FIELD', 'kyc_transaction_id'),
    ],

    /**
     * QR Code configuration
     */
    'qr_code' => [
        'enabled' => env('HYPERVERGE_QR_ENABLED', true),
        'default_size' => 300,
        'margin' => 10,
        'error_correction' => 'H', // L (7%), M (15%), Q (25%), H (30%)
    ],

    /**
     * Face Verification configuration
     */
    'face_verification' => [
        'enabled' => env('HYPERVERGE_FACE_VERIFICATION_ENABLED', true),
        
        // Liveness check settings
        'require_liveness' => env('HYPERVERGE_FACE_LIVENESS_REQUIRED', true),
        'min_liveness_score' => env('HYPERVERGE_FACE_MIN_LIVENESS', 0.8),
        
        // Face match settings
        'min_match_confidence' => env('HYPERVERGE_FACE_MIN_MATCH', 0.85),
        
        // Storage settings
        'store_verification_attempts' => env('HYPERVERGE_STORE_FACE_ATTEMPTS', true),
        'attempts_retention_days' => env('HYPERVERGE_FACE_ATTEMPTS_RETENTION', 30),
        
        // Image validation
        'max_file_size' => 5 * 1024 * 1024, // 5MB
        'min_width' => 200,
        'min_height' => 200,
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/jpg'],
    ],

    /**
     * Document Signing configuration
     */
    'document_signing' => [
        'enabled' => env('HYPERVERGE_DOCUMENT_SIGNING_ENABLED', true),
        'auto_sign_on_approval' => env('HYPERVERGE_AUTO_SIGN_ON_APPROVAL', false),

        /**
         * Signature stamp configuration
         */
        'stamp' => [
            'width' => 1500,
            'height' => 800,

            // Logo watermark overlay
            'logo' => [
                'file' => env('HYPERVERGE_STAMP_LOGO', 'logo.png'), // Relative to public/images or absolute path
                'opacity' => 50,
                'angle' => -45,
                'position' => 'center',
            ],

            // Timestamp banner
            'timestamp' => [
                'font' => 'DejaVuSans.ttf', // Intervention/Image bundled font
                'size' => 32,
                'color' => '#FFFFFF',
                'background' => '#36454F',
                'format' => 'D d Hi\\H M Y eO', // Example: Mon 20 1430H Jan 2025 UTC+0
            ],

            // Metadata display (name, email, etc.)
            'metadata' => [
                'font' => 'DejaVuSans.ttf',
                'size' => 16,
                'color' => '#333333',
                'position' => 'top-right',
            ],

            // Verification QR code
            'qr_code' => [
                'size' => 200,
                'position' => 'bottom-left',
                'opacity' => 90,
            ],
        ],

        /**
         * PDF watermark configuration
         */
        'watermark' => [
            'resolution' => 300, // DPI
            'quality' => 100,

            // Tile positions for multi-signature support (3x3 grid)
            'tile_positions' => [
                1 => ['vertical' => 'bottom', 'horizontal' => 'right', 'offsetX' => 10, 'offsetY' => 10],
                2 => ['vertical' => 'bottom', 'horizontal' => 'center', 'offsetX' => 0, 'offsetY' => 10],
                3 => ['vertical' => 'bottom', 'horizontal' => 'left', 'offsetX' => 10, 'offsetY' => 10],
                4 => ['vertical' => 'center', 'horizontal' => 'right', 'offsetX' => 10, 'offsetY' => 0],
                5 => ['vertical' => 'center', 'horizontal' => 'center', 'offsetX' => 0, 'offsetY' => 0],
                6 => ['vertical' => 'center', 'horizontal' => 'left', 'offsetX' => 10, 'offsetY' => 0],
                7 => ['vertical' => 'top', 'horizontal' => 'right', 'offsetX' => 10, 'offsetY' => 10],
                8 => ['vertical' => 'top', 'horizontal' => 'center', 'offsetX' => 0, 'offsetY' => 10],
                9 => ['vertical' => 'top', 'horizontal' => 'left', 'offsetX' => 10, 'offsetY' => 10],
            ],
        ],

        /**
         * Tile allocation settings
         */
        'tiles' => [
            'max' => 9, // 3x3 grid
            'columns' => 3,
            'rows' => 3,
        ],

        /**
         * Media collections for document storage
         */
        'media_collections' => [
            'documents',           // Original uploaded documents
            'signed_documents',    // Watermarked/signed versions
            'signature_marks',     // Stamp images
            'tracked_documents',   // Documents with tracking QR codes
        ],

        /**
         * Tracking QR code configuration
         */
        'tracking' => [
            'qr_code_size' => 150,
            'position' => 'top-right',
            'page' => 1, // First page only
        ],

        /**
         * QR code watermark on signed PDFs
         */
        'qr_watermark' => [
            'enabled' => true,
            'position' => 'bottom-right', // Position on PDF
            'size' => 100, // QR size in pixels (about 1 inch at 300 DPI)
            'page' => -1, // Last page (-1) or all pages (0)
            'opacity' => 100,
        ],

        /**
         * Temporary file storage
         */
        'temp_dir' => 'tmp/document-signing',
    ],
];
