<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk Firebase services termasuk FCM
    |
    */

    'default' => 'app',

    'projects' => [
        'app' => [
            'credentials' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase/firebase_credentials.json')),
            'auth' => [
                'tenant_id' => env('FIREBASE_AUTH_TENANT_ID'),
            ],
            'firestore' => [],
            'database' => [
                'url' => env('FIREBASE_DATABASE_URL'),
            ],
            'dynamic_links' => [
                'default_domain' => env('FIREBASE_DYNAMIC_LINKS_DEFAULT_DOMAIN'),
            ],
            'storage' => [
                'default_bucket' => env('FIREBASE_STORAGE_BUCKET'),
            ],
            'cache_store' => env('FIREBASE_CACHE_STORE', 'file'),
            'logging' => [
                'http_log_channel' => env('FIREBASE_HTTP_LOG_CHANNEL'),
                'http_debug_log_channel' => env('FIREBASE_HTTP_DEBUG_LOG_CHANNEL'),
            ],
            'http_client_options' => [
                'proxy' => env('FIREBASE_HTTP_CLIENT_PROXY'),
                'timeout' => env('FIREBASE_HTTP_CLIENT_TIMEOUT'),
                'guzzle_middlewares' => [],
            ],
        ],
    ],

    'project_id' => env('FIREBASE_PROJECT_ID'),
    'webhook_secret' => env('FIREBASE_WEBHOOK_SECRET', 'your-webhook-secret-key'),
    
    'credentials_path' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase/firebase_credentials.json')),
    
    'database_url' => env('FIREBASE_DATABASE_URL'),
    
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
    
    'auth' => [
        'emulator_host' => env('FIREBASE_AUTH_EMULATOR_HOST'),
    ],
    
    'firestore' => [
        'emulator_host' => env('FIRESTORE_EMULATOR_HOST'),
    ],
];
