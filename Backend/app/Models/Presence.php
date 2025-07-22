<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Helpers\CloudinaryHelper;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Log;
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
                        Cloudinary::destroy($publicId);
                        Log::info("Berhasil menghapus foto {$type} dari Cloudinary: {$publicId}");
                    } catch (\Exception $e) {
                        Log::error("Gagal menghapus foto {$type} dari Cloudinary: {$publicId}, Error: {$e->getMessage()}");
                        Notification::make()
                            ->title("Gagal Menghapus Foto {$type}")
                            ->body("Gagal menghapus foto dari Cloudinary: {$e->getMessage()}")
                            ->danger()
                            ->send();
                    }
                }
            }
        });
    }
}