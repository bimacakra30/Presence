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

    'project_id' => env('FIREBASE_PROJECT_ID'),
    
    'credentials_path' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase/firebase_credentials.json')),
    
    'database_url' => env('FIREBASE_DATABASE_URL'),
    
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
    
    'messaging' => [
        'default_topic' => env('FCM_DEFAULT_TOPIC', 'general'),
        'timeout' => env('FCM_TIMEOUT', 30),
        'retry_attempts' => env('FCM_RETRY_ATTEMPTS', 3),
    ],
    
    'auth' => [
        'emulator_host' => env('FIREBASE_AUTH_EMULATOR_HOST'),
    ],
    
    'firestore' => [
        'emulator_host' => env('FIRESTORE_EMULATOR_HOST'),
    ],
];
