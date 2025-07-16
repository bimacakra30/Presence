<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'photo',
        'name',
        'email',
        'phone',
        'address',
        'date_of_birth',
        'position',
        'salary',
        'provider',
        'status',
        'firestore_id',
    ];

    protected static function booted()
    {
        // Create
        static::created(function ($employee) {
            $service = new \App\Services\FirestoreService();

            $data = [
                'username' => $employee->name,
                'email' => $employee->email,
                'status' => $employee->status,
                'createdAt' => now()->toISOString(),
            ];

            $collection = $service->getCollection();
            $docRef = $collection->add($data);

            $firestoreId = $docRef->id();

            $employee->firestore_id = $firestoreId;
            $employee->saveQuietly();
        });

        // Update
        static::updated(function ($employee) {
            if ($employee->firestore_id) {
                $service = new \App\Services\FirestoreService();

                $data = [
                    'username' => $employee->name,
                    'email' => $employee->email,
                    'status' => $employee->status,
                ];

                $service->updateUser($employee->firestore_id, $data);
            }
        });

        // Delete — only when force delete
        static::deleted(function ($employee) {
            Log::info("Deleted event called. Firestore ID: " . $employee->firestore_id);

            if ($employee->firestore_id) {
                $service = new \App\Services\FirestoreService();
                $service->deleteUser($employee->firestore_id);
            }
        });
    }
}
