<?php

return [
    'cloud_url'    => env('CLOUDINARY_URL'),

    'cloud_name'   => env('CLOUDINARY_CLOUD_NAME'),
    'api_key'      => env('CLOUDINARY_API_KEY'),
    'api_secret'   => env('CLOUDINARY_API_SECRET'),

    // Tetap pakai key 'url', tapi pastikan isinya array
    'url' => [
        'secure' => true,
    ],

    'upload_preset' => (string) env('CLOUDINARY_UPLOAD_PRESET', ''),
];
