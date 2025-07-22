<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'photo',
        'name',
        'username',
        'email',
        'phone',
        'password',
        'address',
        'date_of_birth',
        'status',
        'provider',
        'firestore_id',
    ];

    protected $hidden = [
        'password',
    ];


    public function setPasswordAttribute($value)
    {
        if (!str_starts_with($value, '$2y$')) {
            $this->attributes['password'] = Hash::make($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }

    protected static function booted()
    {
        // Create

        static::creating(function ($employee) {
            if (!$employee->uid) {
                $employee->uid = (string) \Illuminate\Support\Str::uuid();
            }
        });
        static::created(function ($employee) {
            $firestoreService = new \App\Services\FirestoreService();

            $data = [
                'uid' => $employee->uid,
                'name' => $employee->name,
                'username' => $employee->username,
                'email' => $employee->email,
                'password' => $employee->password,
                'status' => $employee->status,
                'provider' => $employee->provider,
                'createdAt' => now()->toISOString(),
            ];

            // Simpan ke Firestore
            $collection = $firestoreService->getCollection();
            $docRef = $collection->add($data);
            $employee->firestore_id = $docRef->id();
            $employee->saveQuietly();

            // Simpan ke Firebase Auth
            try {
                $auth = app('firebase.auth');

                $auth->createUser([
                    'uid' => $employee->uid,
                    'email' => $employee->email,
                    'password' => $employee->password, // Sudah di-hash, akan error jika tidak plain
                    'displayName' => $employee->name,
                ]);
            } catch (\Kreait\Firebase\Exception\Auth\EmailExists $e) {
                Log::warning('Email already exists in Firebase Auth: ' . $employee->email);
            } catch (\Throwable $e) {
                Log::error('Failed to create Firebase Auth user: ' . $e->getMessage());
            }
        });


        // Update
        static::updated(function ($employee) {
            if ($employee->firestore_id) {
                $service = new \App\Services\FirestoreService();

                $data = [
                    'email' => $employee->email,
                    'status' => $employee->status,
                    'provider' => $employee->provider,
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

    public function positions()
    {
        return $this->hasMany(EmployeePosition::class, 'employee_id');
    }
    public function salaries()
    {
        return $this->hasMany(EmployeeSalary::class, 'employee_id');
    }

    public function presences()
    {
        return $this->hasMany(Presence::class, 'employee_id');
    }
}
