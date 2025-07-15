<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
    ];

    protected static function booted()
    {
        static::created(function ($employee) {
            $service = new \App\Services\FirestoreService();

            $data = [
                'username' => $employee->name,
                'email' => $employee->email,
                'createdAt' => now()->toISOString(),
            ];

            // Create document tanpa ID → Firestore auto-generate ID random
            $collection = $service->getCollection();
            $docRef = $collection->add($data);

            // Ambil ID random Firestore
            $firestoreId = $docRef->id();

            // Update kolom firestore_id di DB lokal
            $employee->firestore_id = $firestoreId;
            $employee->saveQuietly();
        });

        static::updated(function ($employee) {
            if ($employee->firestore_id && $employee->isDirty('email')) {
                $authService = new \App\Services\FirebaseAuthService();

                try {
                    // Get user Auth by old email
                    $userAuth = $authService->getUserByEmail($employee->getOriginal('email'));

                    // Update email Auth
                    $authService->updateEmail($userAuth->uid, $employee->email);

                } catch (\Exception $e) {
                    Log::error('Failed to update Firebase Auth email: ' . $e->getMessage());
                }
            }

            if ($employee->firestore_id) {
                $service = new \App\Services\FirestoreService();

                $data = [
                    'username' => $employee->name,
                    'email' => $employee->email,
                ];

                $service->updateUser($employee->firestore_id, $data);
            }
        });

        static::deleted(function ($employee) {
            if ($employee->firestore_id) {
                $service = new \App\Services\FirestoreService();
                $service->deleteUser($employee->firestore_id);
            }
        });
    }
}
