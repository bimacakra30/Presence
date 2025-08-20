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
        'position',
        'status',
        'provider',
        'firestore_id',
    ];

    protected $hidden = [
        'password',
    ];

    // Temporary property untuk menyimpan plain password
    protected $plainPassword;

    public function setPasswordAttribute($value)
    {
        // Simpan plain password sementara
        $this->plainPassword = $value;

        // Hash password untuk disimpan di database lokal
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

            // Data untuk Firestore dengan password yang di-hash
            $data = [
                'uid' => $employee->uid,
                'profilePictureUrl' => $employee->photo,
                'name' => $employee->name,
                'username' => $employee->username,
                'email' => $employee->email,
                'password' => Hash::make($employee->plainPassword), // Hash password untuk Firestore
                'address' => $employee->address,
                'dateOfBirth' => $employee->date_of_birth,
                'position' => $employee->position,
                'status' => $employee->status,
                'provider' => $employee->provider,
                'createdAt' => now()->toISOString(),
            ];

            // Simpan ke Firestore
            try {
                $collection = $firestoreService->getCollection();
                $docRef = $collection->add($data);
                $employee->firestore_id = $docRef->id();
                $employee->saveQuietly();

                // Simpan ke Firebase Auth dengan plain password
                $auth = app('firebase.auth');
                $auth->createUser([
                    'uid' => $employee->uid,
                    'email' => $employee->email,
                    'password' => $employee->plainPassword, // Gunakan plain password
                    'displayName' => $employee->name,
                ]);

                // Notifikasi sukses
                \Filament\Notifications\Notification::make()
                    ->title('Employee Created')
                    ->body('Employee ' . $employee->name . ' has been created and synced with Firestore and Firebase Authentication.')
                    ->success()
                    ->send();
            } catch (\Kreait\Firebase\Exception\Auth\EmailExists $e) {
                Log::warning('Email already exists in Firebase Auth: ' . $employee->email);
                \Filament\Notifications\Notification::make()
                    ->title('Warning')
                    ->body('Employee created in Firestore, but email already exists in Firebase Authentication.')
                    ->warning()
                    ->send();
            } catch (\Throwable $e) {
                Log::error('Failed to create employee in Firestore or Firebase Auth: ' . $e->getMessage());
                \Filament\Notifications\Notification::make()
                    ->title('Error')
                    ->body('Failed to create employee in Firestore or Firebase Authentication: ' . $e->getMessage())
                    ->danger()
                    ->send();
            }

            // Hapus plain password setelah digunakan
            unset($employee->plainPassword);
        });

        // Update
        static::updated(function ($employee) {
            if ($employee->firestore_id) {
                $service = new \App\Services\FirestoreService();

                $data = [
                    'email' => $employee->email,
                    'status' => $employee->status,
                    'position' => $employee->position,
                    'provider' => $employee->provider,
                ];

                try {
                    $service->updateUser($employee->firestore_id, $data);
                    \Filament\Notifications\Notification::make()
                        ->title('Employee Updated')
                        ->body('Employee ' . $employee->name . ' has been updated in Firestore.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Log::error('Failed to update employee in Firestore: ' . $e->getMessage());
                    \Filament\Notifications\Notification::make()
                        ->title('Error')
                        ->body('Failed to update employee in Firestore: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }
        });

        // Delete — only when force delete
        static::deleted(function ($employee) {
            Log::info("Deleted event called. Firestore ID: " . $employee->firestore_id);

            if ($employee->firestore_id) {
                $service = new \App\Services\FirestoreService();
                try {
                    $service->deleteUser($employee->firestore_id);
                    // Hapus dari Firebase Auth
                    $auth = app('firebase.auth');
                    $auth->deleteUser($employee->uid);
                    \Filament\Notifications\Notification::make()
                        ->title('Employee Deleted')
                        ->body('Employee ' . $employee->name . ' has been deleted from Firestore and Firebase Authentication.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Log::error('Failed to delete employee from Firestore or Firebase Auth: ' . $e->getMessage());
                    \Filament\Notifications\Notification::make()
                        ->title('Error')
                        ->body('Failed to delete employee from Firestore or Firebase Authentication: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }
        });
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