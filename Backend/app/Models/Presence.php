<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;

class Presence extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'firestore_id',
        'nama',
        'tanggal',
        'clock_in',
        'clock_out',
        'foto_clock_in',
        'foto_clock_out',
        'public_id_clock_in',
        'public_id_clock_out',
        'status',
        'durasi_keterlambatan'
    ];

    protected static function booted()
    {
        static::deleted(function ($presence) {
            // Firestore deletion
            if ($presence->firestore_id) {
                try {
                    $service = new \App\Services\FirestoreService();
                    $service->deleteAbsensi($presence->firestore_id);
                    Log::info("Berhasil menghapus data Firestore dengan ID: {$presence->firestore_id}");
                } catch (\Exception $e) {
                    Log::error("Gagal menghapus data Firestore: {$presence->firestore_id}, Error: {$e->getMessage()}");
                    Notification::make()
                        ->title('Gagal Menghapus Firestore')
                        ->body("Gagal menghapus data Firestore: {$e->getMessage()}")
                        ->danger()
                        ->send();
                }
            }

            // Cloudinary photo deletion
            foreach ([
                'clock_in' => $presence->public_id_clock_in,
                'clock_out' => $presence->public_id_clock_out,
            ] as $type => $publicId) {
                if ($publicId) {
                    try {
                        $cloudName = env('CLOUDINARY_CLOUD_NAME', 'dyf9r2al9');
                        $apiKey = env('CLOUDINARY_API_KEY', '783185659825456');
                        $apiSecret = env('CLOUDINARY_API_SECRET', 'MffoiYNXsoxv7Zlvo3GPFCYBLOE');

                        // Log konfigurasi
                        Log::info("Konfigurasi Cloudinary untuk {$type}: " . json_encode([
                            'cloud_name' => $cloudName,
                            'api_key' => $apiKey,
                            'public_id' => $publicId,
                        ]));

                        // Buat signature untuk otentikasi
                        $timestamp = time();
                        $signature = sha1("public_id=$publicId&timestamp=$timestamp$apiSecret");

                        // Kirim request ke API Cloudinary
                        $response = Http::post("https://api.cloudinary.com/v1_1/$cloudName/image/destroy", [
                            'public_id' => $publicId,
                            'api_key' => $apiKey,
                            'timestamp' => $timestamp,
                            'signature' => $signature,
                        ]);

                        if ($response->successful()) {
                            Log::info("Berhasil menghapus foto {$type} dari Cloudinary: {$publicId}");
                        } else {
                            throw new \Exception("Gagal menghapus foto dari Cloudinary: " . $response->body());
                        }
                    } catch (\Exception $e) {
                        Log::error("Gagal menghapus foto {$type} dari Cloudinary: {$publicId}, Error: {$e->getMessage()}");
                        Notification::make()
                            ->title("Gagal Menghapus Foto {$type}")
                            ->body("Gagal menghapus foto dari Cloudinary: {$e->getMessage()}")
                            ->danger()
                            ->send();
                    }
                } else {
                    Log::warning("Public ID untuk {$type} tidak ditemukan, lewati penghapusan Cloudinary.");
                }
            }
        });
    }
}