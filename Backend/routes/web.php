<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FirestoreSyncController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sync-firestore', [FirestoreSyncController::class, 'sync'])->name('sync.firestore');

Route::get('/test-cloudinary-config', function () {
    try {
        $cloudinaryUrl = env('CLOUDINARY_URL', 'cloudinary://783185659825456:MffoiYNXsoxv7Zlvo3GPFCYBLOE@dyf9r2al9');
        \Cloudinary\Configuration\Configuration::instance($cloudinaryUrl);
        $config = \Cloudinary\Configuration\Configuration::instance();
        return response()->json([
            'config' => [
                'cloud_name' => $config->cloud->cloudName,
                'api_key' => $config->cloud->apiKey,
                'secure' => $config->url->secure,
            ],
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            'api_key' => env('CLOUDINARY_API_KEY'),
            'cloudinary_url' => $cloudinaryUrl,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
Route::get('/test-cloudinary-http-delete', function () {
    try {
        $publicId = 'sqncpcxoh0ora33mmsfh'; // Pastikan ID ini valid
        $cloudName = env('CLOUDINARY_CLOUD_NAME', 'dyf9r2al9');
        $apiKey = env('CLOUDINARY_API_KEY', '783185659825456');
        $apiSecret = env('CLOUDINARY_API_SECRET', 'MffoiYNXsoxv7Zlvo3GPFCYBLOE');

        $timestamp = time();
        $signature = sha1("public_id=$publicId&timestamp=$timestamp$apiSecret");

        $response = \Illuminate\Support\Facades\Http::post("https://api.cloudinary.com/v1_1/$cloudName/image/destroy", [
            'public_id' => $publicId,
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        if ($response->successful()) {
            \Illuminate\Support\Facades\Log::info("Berhasil menghapus foto dari Cloudinary: {$publicId}");
            return response()->json(['message' => 'Foto berhasil dihapus']);
        } else {
            throw new \Exception("Gagal menghapus foto: " . $response->body());
        }
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error("Gagal menghapus foto dari Cloudinary: {$publicId}, Error: {$e->getMessage()}");
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
Route::get('/test-env', function () {
    return response()->json([
        'CLOUDINARY_CLOUD_NAME' => env('CLOUDINARY_CLOUD_NAME'),
        'CLOUDINARY_API_KEY' => env('CLOUDINARY_API_KEY'),
        'CLOUDINARY_API_SECRET' => env('CLOUDINARY_API_SECRET', 'secret_not_shown'),
        'CLOUDINARY_URL' => env('CLOUDINARY_URL'),
    ]);
});