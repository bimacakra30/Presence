<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Permit extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_karyawan',
        'jenis_perizinan',
        'tanggal_mulai',
        'tanggal_selesai',
        'deskripsi',
        'bukti_izin',
        'status',
        'uid',
        'firestore_id',
        'bukti_izin_public_id'
    ];

    protected static function booted()
    {
        static::deleted(function ($permit) {
            // Firestore deletion
            if ($permit->firestore_id) {
                try {
                    $service = new \App\Services\FirestoreService();
                    $service->deletePerizinan($permit->firestore_id);
                    Log::info("Berhasil menghapus data Firestore dengan ID: {$permit->firestore_id}");
                } catch (\Exception $e) {
                    Log::error("Gagal menghapus data Firestore: {$permit->firestore_id}, Error: {$e->getMessage()}");
                    throw new \Exception("Gagal menghapus data Firestore: {$e->getMessage()}");
                }
            }

            // Cloudinary photo deletion
            foreach ([
                'bukti_izin' => $permit->bukti_izin_public_id,
            ] as $type => $publicId) {
                if ($publicId) {
                    try {
                        $cloudName = env('CLOUDINARY_CLOUD_NAME', 'dyf9r2al9');
                        $apiKey = env('CLOUDINARY_API_KEY', '783185659825456');
                        $apiSecret = env('CLOUDINARY_API_SECRET', 'MffoiYNXsoxv7Zlvo3GPFCYBLOE');

                        // Log konfigurasi
                        Log::info("Konfigurasi Cloudinary: " . json_encode([
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
                        throw new \Exception("Gagal menghapus foto {$type} dari Cloudinary: {$e->getMessage()}");
                    }
                } else {
                    Log::warning("Public ID untuk {$type} tidak ditemukan, lewati penghapusan Cloudinary.");
                }
            }
        });
    }
}