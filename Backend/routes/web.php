<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

// Proxy untuk gambar dari Firebase/Google
Route::get('/image-proxy', function (Request $request) {
    $url = $request->get('url');
    
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        abort(404);
    }
    
    // Hanya izinkan URL dari domain yang dipercaya
    $allowedDomains = [
        'lh3.googleusercontent.com',
        'firebasestorage.googleapis.com',
        'storage.googleapis.com'
    ];
    
    $parsedUrl = parse_url($url);
    if (!in_array($parsedUrl['host'] ?? '', $allowedDomains)) {
        abort(403);
    }
    
    try {
        $imageData = file_get_contents($url);
        $contentType = 'image/jpeg'; // Default
        
        // Deteksi content type dari header
        $headers = get_headers($url, 1);
        if (isset($headers['Content-Type'])) {
            $contentType = is_array($headers['Content-Type']) 
                ? $headers['Content-Type'][0] 
                : $headers['Content-Type'];
        }
        
        return response($imageData)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'public, max-age=3600');
    } catch (Exception $e) {
        abort(404);
    }
})->name('image.proxy');