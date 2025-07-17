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

    protected static function booted()
    {
        // Create
        static::created(function ($employee) {
            $service = new \App\Services\FirestoreService();

            $data = [
                'name' => $employee->name,
                'username' => $employee->username,
                'email' => $employee->email,
                'password' => Hash::make($employee->password),
                'status' => $employee->status,
                'provider' => $employee->provider,
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
