<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class EmployeePosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'position',
        'start_date',
        'end_date',
    ];

    protected static function booted()
    {
        // Create
        static::created(function ($employee) {
            $service = new \App\Services\FirestoreService();

            $data = [
                'employee_id' => $employee->employee_id,
                'position' => $employee->position,
                'start_date' => $employee->start_date,
                'end_date' => $employee->end_date,
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
                    'employee_id' => $employee->employee_id,
                    'position' => $employee->position,
                    'start_date' => $employee->start_date->toISOString(),
                    'end_date' => $employee->end_date ? $employee->end_date->toISOString() : null,
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

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
