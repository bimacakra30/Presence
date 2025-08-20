<?php

namespace App\Services;

use App\Models\Employee;
use App\Services\FirestoreService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FirestoreSyncService
{
    protected FirestoreService $firestoreService;

    public function __construct()
    {
        $this->firestoreService = new FirestoreService();
    }

    /**
     * Sync semua data dari Firestore ke database lokal
     */
    public function syncAllEmployees(): array
    {
        $firestoreUsers = $this->firestoreService->getUsers(false); // Force fresh data
        
        $synced = 0;
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($firestoreUsers as $firestoreUser) {
            try {
                $result = $this->syncSingleEmployee($firestoreUser);
                
                if ($result['action'] === 'created') {
                    $created++;
                } elseif ($result['action'] === 'updated') {
                    $updated++;
                }
                $synced++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'user' => $firestoreUser['email'] ?? 'Unknown',
                    'error' => $e->getMessage()
                ];
                Log::error('Failed to sync employee: ' . $e->getMessage(), $firestoreUser);
            }
        }

        // Log summary
        Log::info("Firestore sync completed: {$synced} synced, {$created} created, {$updated} updated, " . count($errors) . " errors");

        return [
            'synced' => $synced,
            'created' => $created, 
            'updated' => $updated,
            'errors' => $errors
        ];
    }

    /**
     * Sync single employee from Firestore data
     */
    public function syncSingleEmployee(array $firestoreUser): array
    {
        $uid = $firestoreUser['uid'] ?? null;
        $firestoreId = $firestoreUser['id'] ?? null;

        if (!$uid && !$firestoreId) {
            throw new \Exception('No UID or Firestore ID found');
        }

        // Cari employee berdasarkan uid atau firestore_id
        $employee = null;
        if ($uid) {
            $employee = Employee::where('uid', $uid)->first();
        }
        if (!$employee && $firestoreId) {
            $employee = Employee::where('firestore_id', $firestoreId)->first();
        }

        // Prepare data untuk sync
        $syncData = $this->prepareEmployeeData($firestoreUser);

        if ($employee) {
            // Update existing employee
            $hasChanges = $this->hasDataChanges($employee, $syncData);
            
            if ($hasChanges) {
                // Update tanpa trigger model events (untuk avoid infinite loop)
                Employee::where('id', $employee->id)->update($syncData);
                
                Log::info("Employee updated from Firestore: {$employee->email}");
                return ['action' => 'updated', 'employee' => $employee];
            }
            
            return ['action' => 'no_change', 'employee' => $employee];
            
        } else {
            // Create new employee
            $syncData['uid'] = $uid ?: (string) \Illuminate\Support\Str::uuid();
            $syncData['firestore_id'] = $firestoreId;
            
            // Create tanpa trigger events
            $employee = new Employee();
            $employee->fill($syncData);
            $employee->saveQuietly();
            
            Log::info("Employee created from Firestore: {$syncData['email']}");
            return ['action' => 'created', 'employee' => $employee];
        }
    }

    /**
     * Prepare data dari Firestore untuk Laravel model
     */
    protected function prepareEmployeeData(array $firestoreUser): array
    {
        $data = [
            'name' => $firestoreUser['name'] ?? '',
            'username' => $firestoreUser['username'] ?? '',
            'email' => $firestoreUser['email'] ?? '',
            'phone' => $firestoreUser['phone'] ?? null,
            'address' => $firestoreUser['address'] ?? null,
            'position' => $firestoreUser['position'] ?? '',
            'status' => $firestoreUser['status'] ?? 'aktif',
            'provider' => $firestoreUser['provider'] ?? 'google',
        ];

        // Handle photo URL
        if (!empty($firestoreUser['profilePictureUrl'])) {
            $data['photo'] = $firestoreUser['profilePictureUrl'];
        }

        // Handle date of birth
        if (!empty($firestoreUser['dateOfBirth'])) {
            try {
                $data['date_of_birth'] = Carbon::parse($firestoreUser['dateOfBirth'])->format('Y-m-d');
            } catch (\Exception $e) {
                // Ignore invalid dates
                $data['date_of_birth'] = null;
            }
        }

        // Don't sync password - keep existing or set default
        // $data['password'] = 'synced_from_firestore'; // Optional

        return $data;
    }

    /**
     * Check if there are any changes between current model and Firestore data
     */
    protected function hasDataChanges(Employee $employee, array $newData): bool
    {
        $fieldsToCheck = ['name', 'username', 'email', 'phone', 'address', 'position', 'status', 'provider', 'photo'];

        foreach ($fieldsToCheck as $field) {
            $currentValue = $employee->getAttribute($field);
            $newValue = $newData[$field] ?? null;

            // Normalize null values
            if (empty($currentValue)) $currentValue = null;
            if (empty($newValue)) $newValue = null;

            if ($currentValue !== $newValue) {
                Log::debug("Field '{$field}' changed: '{$currentValue}' -> '{$newValue}'");
                return true;
            }
        }

        // Check date_of_birth separately
        $currentDate = $employee->date_of_birth?->format('Y-m-d');
        $newDate = $newData['date_of_birth'] ?? null;
        
        if ($currentDate !== $newDate) {
            Log::debug("Date of birth changed: '{$currentDate}' -> '{$newDate}'");
            return true;
        }

        return false;
    }

    /**
     * Sync specific employee by UID
     */
    public function syncEmployeeByUid(string $uid): array
    {
        $firestoreUsers = $this->firestoreService->searchUsers('uid', $uid);
        
        if (empty($firestoreUsers)) {
            throw new \Exception("Employee with UID {$uid} not found in Firestore");
        }

        return $this->syncSingleEmployee($firestoreUsers[0]);
    }

    /**
     * Sync employees yang diupdate dalam X menit terakhir (jika Firestore punya timestamp)
     */
    public function syncRecentChanges(int $minutesAgo = 5): array
    {
        // This would require updatedAt field in Firestore
        // For now, just sync all and let the change detection handle it
        return $this->syncAllEmployees();
    }

    /**
     * Delete employees dari database lokal jika sudah tidak ada di Firestore
     */
    public function cleanupDeletedEmployees(): array
    {
        $firestoreUsers = $this->firestoreService->getUsers(false);
        $firestoreUids = collect($firestoreUsers)->pluck('uid')->filter()->toArray();
        
        // Find local employees yang tidak ada di Firestore
        $deletedEmployees = Employee::whereNotNull('uid')
            ->whereNotIn('uid', $firestoreUids)
            ->get();

        $deleted = 0;
        foreach ($deletedEmployees as $employee) {
            try {
                $employee->delete();
                $deleted++;
                Log::info("Deleted employee (not found in Firestore): {$employee->email}");
            } catch (\Exception $e) {
                Log::error("Failed to delete employee {$employee->email}: " . $e->getMessage());
            }
        }

        return [
            'deleted' => $deleted,
            'employees' => $deletedEmployees->pluck('email')->toArray()
        ];
    }

    /**
     * Full sync: sync all + cleanup deleted
     */
    public function fullSync(): array
    {
        $syncResult = $this->syncAllEmployees();
        $cleanupResult = $this->cleanupDeletedEmployees();

        return array_merge($syncResult, [
            'deleted' => $cleanupResult['deleted'],
            'deleted_employees' => $cleanupResult['employees']
        ]);
    }
}