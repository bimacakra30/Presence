<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class GeoLocator extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_lokasi',
        'deskripsi',
        'latitude',
        'longitude',
        'radius',
        'firestore_id'
    ];

    protected static function booted()
    {
        // CREATE
        static::created(function ($geo) {
            $firestoreService = new \App\Services\FirestoreService();

            $data = [
                'nama_lokasi' => $geo->nama_lokasi,
                'deskripsi'   => $geo->deskripsi,
                'latitude'    => $geo->latitude,
                'longitude'   => $geo->longitude,
                'radius'      => $geo->radius,
                'createdAt'   => now()->toISOString(),
            ];

            // Simpan ke Firestore
            $collection = $firestoreService->getCollectionMaps('geo_locator');
            $docRef = $collection->add($data);

            // Simpan Firestore document ID di MySQL
            $geo->firestore_id = $docRef->id();
            $geo->saveQuietly();
        });

        // UPDATE
        static::updated(function ($geo) {
            if ($geo->firestore_id) {
                $firestoreService = new \App\Services\FirestoreService();

                $data = [
                    'nama_lokasi' => $geo->nama_lokasi,
                    'deskripsi'   => $geo->deskripsi,
                    'latitude'    => $geo->latitude,
                    'longitude'   => $geo->longitude,
                    'radius'      => $geo->radius,
                    'updatedAt'   => now()->toISOString(),
                ];

                $firestoreService->updateMaps('geo_locator', $geo->firestore_id, $data);
            }
        });

        // DELETE
        static::deleted(function ($geo) {
            if ($geo->firestore_id) {
                $firestoreService = new \App\Services\FirestoreService();
                $firestoreService->deleteMaps('geo_locator', $geo->firestore_id);
            }
        });
    }
}
