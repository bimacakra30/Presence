<?php

namespace App\Services;

use App\Models\Employee;
use App\Services\FirestoreService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ManualFirestoreSyncService
{
    protected FirestoreService $firestoreService;
    protected array $syncStats = [];

    public function __construct()
    {
        $this->firestoreService = new FirestoreService();
    }

    /**
     * Sync all employees from Firestore with detailed logging
     */
    public function syncAllEmployees(bool $force = false): array
    {
        $this->resetStats();
        $startTime = now();
        
        Log::info('Manual sync: Starting sync all employees', [
            'force' => $force,
            'start_time' => $startTime->toISOString()
        ]);

        try {
            $firestoreUsers = $this->firestoreService->getUsers(false); // Force fresh data
            $this->syncStats['total_firestore'] = count($firestoreUsers);
            
            Log::info('Manual sync: Retrieved employees from Firestore', [
                'count' => count($firestoreUsers)
            ]);

            foreach ($firestoreUsers as $firestoreUser) {
                try {
                    $result = $this->syncSingleEmployee($firestoreUser, $force);
                    $this->updateStats($result);
                    
                } catch (\Exception $e) {
                    $this->syncStats['errors']++;
                    $this->syncStats['error_details'][] = [
                        'user' => $firestoreUser['email'] ?? 'Unknown',
                        'uid' => $firestoreUser['uid'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                    Log::error('Manual sync: Failed to sync employee', [
                        'user' => $firestoreUser['email'] ?? 'Unknown',
                        'uid' => $firestoreUser['uid'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);
            $this->syncStats['duration_seconds'] = $duration;

            Log::info('Manual sync: Completed sync all employees', [
                'duration' => $duration,
                'stats' => $this->syncStats
            ]);

            return $this->getSyncResult();

        } catch (\Exception $e) {
            Log::error('Manual sync: Failed to sync all employees', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Sync specific employee by UID with detailed logging
     */
    public function syncEmployeeByUid(string $uid, bool $force = false): array
    {
        $this->resetStats();
        $startTime = now();
        
        Log::info('Manual sync: Starting sync by UID', [
            'uid' => $uid,
            'force' => $force,
            'start_time' => $startTime->toISOString()
        ]);

        try {
            $firestoreUsers = $this->firestoreService->searchUsers('uid', $uid);
            
            if (empty($firestoreUsers)) {
                throw new \Exception("Employee with UID {$uid} not found in Firestore");
            }

            $result = $this->syncSingleEmployee($firestoreUsers[0], $force);
            $this->updateStats($result);

            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);
            $this->syncStats['duration_seconds'] = $duration;

            Log::info('Manual sync: Completed sync by UID', [
                'uid' => $uid,
                'duration' => $duration,
                'result' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Manual sync: Failed to sync by UID', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sync specific employee by email with detailed logging
     */
    public function syncEmployeeByEmail(string $email, bool $force = false): array
    {
        $this->resetStats();
        $startTime = now();
        
        Log::info('Manual sync: Starting sync by email', [
            'email' => $email,
            'force' => $force,
            'start_time' => $startTime->toISOString()
        ]);

        try {
            $firestoreUsers = $this->firestoreService->searchUsers('email', $email);
            
            if (empty($firestoreUsers)) {
                throw new \Exception("Employee with email '{$email}' not found in Firestore");
            }

            $result = $this->syncSingleEmployee($firestoreUsers[0], $force);
            $this->updateStats($result);

            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);
            $this->syncStats['duration_seconds'] = $duration;

            Log::info('Manual sync: Completed sync by email', [
                'email' => $email,
                'duration' => $duration,
                'result' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Manual sync: Failed to sync by email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Clean up deleted employees with detailed logging
     */
    public function cleanupDeletedEmployees(): array
    {
        $this->resetStats();
        $startTime = now();
        
        Log::info('Manual sync: Starting cleanup deleted employees', [
            'start_time' => $startTime->toISOString()
        ]);

        try {
            $firestoreUsers = $this->firestoreService->getUsers(false);
            $firestoreUids = collect($firestoreUsers)->pluck('uid')->filter()->toArray();
            
            // Find local employees yang tidak ada di Firestore
            $deletedEmployees = Employee::whereNotNull('uid')
                ->whereNotIn('uid', $firestoreUids)
                ->get();

            $this->syncStats['total_local'] = Employee::count();
            $this->syncStats['total_firestore'] = count($firestoreUids);
            $this->syncStats['to_delete'] = $deletedEmployees->count();

            $deleted = 0;
            $deletedDetails = [];

            foreach ($deletedEmployees as $employee) {
                try {
                    $employee->delete();
                    $deleted++;
                    $deletedDetails[] = [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'uid' => $employee->uid
                    ];
                    
                    Log::info('Manual sync: Deleted employee', [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'uid' => $employee->uid
                    ]);
                    
                } catch (\Exception $e) {
                    $this->syncStats['errors']++;
                    $this->syncStats['error_details'][] = [
                        'user' => $employee->email,
                        'uid' => $employee->uid,
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Manual sync: Failed to delete employee', [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'uid' => $employee->uid,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);
            $this->syncStats['duration_seconds'] = $duration;
            $this->syncStats['deleted'] = $deleted;
            $this->syncStats['deleted_details'] = $deletedDetails;

            Log::info('Manual sync: Completed cleanup deleted employees', [
                'duration' => $duration,
                'deleted' => $deleted,
                'stats' => $this->syncStats
            ]);

            return [
                'deleted' => $deleted,
                'employees' => $deletedDetails,
                'stats' => $this->syncStats
            ];

        } catch (\Exception $e) {
            Log::error('Manual sync: Failed to cleanup deleted employees', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Full sync (sync all + cleanup) with detailed logging
     */
    public function fullSync(bool $force = false): array
    {
        $this->resetStats();
        $startTime = now();
        
        Log::info('Manual sync: Starting full sync', [
            'force' => $force,
            'start_time' => $startTime->toISOString()
        ]);

        try {
            // Step 1: Sync all employees
            $syncResult = $this->syncAllEmployees($force);
            
            // Step 2: Cleanup deleted employees
            $cleanupResult = $this->cleanupDeletedEmployees();

            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);

            $result = array_merge($syncResult, [
                'deleted' => $cleanupResult['deleted'],
                'deleted_employees' => $cleanupResult['employees'],
                'duration_seconds' => $duration,
                'sync_stats' => $this->syncStats
            ]);

            Log::info('Manual sync: Completed full sync', [
                'duration' => $duration,
                'result' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Manual sync: Failed to perform full sync', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Dry run - show what would be synced without making changes
     */
    public function dryRun(): array
    {
        $this->resetStats();
        $startTime = now();
        
        Log::info('Manual sync: Starting dry run', [
            'start_time' => $startTime->toISOString()
        ]);

        try {
            $firestoreUsers = $this->firestoreService->getUsers(false);
            $localEmployees = Employee::all()->keyBy('uid');

            $this->syncStats['total_firestore'] = count($firestoreUsers);
            $this->syncStats['total_local'] = $localEmployees->count();

            $toCreate = 0;
            $toUpdate = 0;
            $noChange = 0;
            $changes = [];

            foreach ($firestoreUsers as $firestoreUser) {
                $uid = $firestoreUser['uid'] ?? null;
                $localEmployee = $localEmployees->get($uid);

                if (!$localEmployee) {
                    $toCreate++;
                    $changes[] = [
                        'action' => 'create',
                        'employee' => [
                            'name' => $firestoreUser['name'] ?? 'N/A',
                            'email' => $firestoreUser['email'] ?? 'N/A',
                            'uid' => $uid
                        ]
                    ];
                } else {
                    $syncData = $this->prepareEmployeeDataForComparison($firestoreUser);
                    if ($this->hasDataChanges($localEmployee, $syncData)) {
                        $toUpdate++;
                        $changes[] = [
                            'action' => 'update',
                            'employee' => [
                                'id' => $localEmployee->id,
                                'name' => $localEmployee->name,
                                'email' => $localEmployee->email,
                                'uid' => $localEmployee->uid
                            ],
                            'changes' => $this->getChangeDetails($localEmployee, $syncData)
                        ];
                    } else {
                        $noChange++;
                    }
                }
            }

            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);

            $result = [
                'summary' => [
                    'to_create' => $toCreate,
                    'to_update' => $toUpdate,
                    'no_change' => $noChange,
                    'total_firestore' => count($firestoreUsers),
                    'total_local' => $localEmployees->count()
                ],
                'changes' => $changes,
                'duration_seconds' => $duration
            ];

            Log::info('Manual sync: Completed dry run', [
                'duration' => $duration,
                'result' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Manual sync: Failed to perform dry run', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get sync status and statistics
     */
    public function getSyncStatus(): array
    {
        try {
            $localCount = Employee::count();
            $firestoreUsers = $this->firestoreService->getUsers(false);
            $firestoreCount = count($firestoreUsers);

            // Get employees with firestore_id
            $syncedCount = Employee::whereNotNull('firestore_id')->count();
            $unsyncedCount = $localCount - $syncedCount;

            // Get recent sync activity (last 24 hours)
            $recentActivity = Employee::where('updated_at', '>=', now()->subDay())
                ->count();

            // Get last sync time from cache
            $lastSyncTime = Cache::get('last_manual_sync_time');

            return [
                'local_employees' => $localCount,
                'firestore_employees' => $firestoreCount,
                'synced_employees' => $syncedCount,
                'unsynced_employees' => $unsyncedCount,
                'recent_activity_24h' => $recentActivity,
                'sync_percentage' => $localCount > 0 ? round(($syncedCount / $localCount) * 100, 2) : 0,
                'last_sync_time' => $lastSyncTime,
                'cache_status' => Cache::has('last_manual_sync_time')
            ];

        } catch (\Exception $e) {
            Log::error('Manual sync: Failed to get sync status', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sync single employee with detailed logging
     */
    protected function syncSingleEmployee(array $firestoreUser, bool $force = false): array
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
            
            if ($hasChanges || $force) {
                // Update tanpa trigger model events (untuk avoid infinite loop)
                Employee::where('id', $employee->id)->update($syncData);
                
                Log::info('Manual sync: Employee updated from Firestore', [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'uid' => $employee->uid,
                    'force' => $force
                ]);
                
                return ['action' => 'updated', 'employee' => $employee->fresh()];
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
            
            Log::info('Manual sync: Employee created from Firestore', [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'uid' => $employee->uid
            ]);
            
            return ['action' => 'created', 'employee' => $employee];
        }
    }

    /**
     * Prepare data dari Firestore untuk Laravel model
     */
    protected function prepareEmployeeData(array $firestoreUser): array
    {
        return [
            'name' => $firestoreUser['name'] ?? '',
            'username' => $firestoreUser['username'] ?? '',
            'email' => $firestoreUser['email'] ?? '',
            'phone' => $firestoreUser['phone'] ?? '',
            'address' => $firestoreUser['address'] ?? '',
            'position' => $firestoreUser['position'] ?? '',
            'status' => $firestoreUser['status'] ?? '',
            'provider' => $firestoreUser['provider'] ?? '',
            'photo' => $firestoreUser['profilePictureUrl'] ?? '',
            'date_of_birth' => !empty($firestoreUser['dateOfBirth']) ? $firestoreUser['dateOfBirth'] : null,
        ];
    }

    /**
     * Check if there are data changes
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
                Log::debug('Manual sync: Field changed', [
                    'field' => $field,
                    'from' => $currentValue,
                    'to' => $newValue
                ]);
                return true;
            }
        }

        // Check date_of_birth separately
        $currentDate = null;
        if ($employee->date_of_birth) {
            if (is_string($employee->date_of_birth)) {
                $currentDate = $employee->date_of_birth;
            } else {
                $currentDate = $employee->date_of_birth->format('Y-m-d');
            }
        }
        $newDate = $newData['date_of_birth'] ?? null;
        
        if ($currentDate !== $newDate) {
            Log::debug('Manual sync: Date of birth changed', [
                'from' => $currentDate,
                'to' => $newDate
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get detailed change information
     */
    protected function getChangeDetails(Employee $employee, array $newData): array
    {
        $changes = [];
        $fieldsToCheck = ['name', 'username', 'email', 'phone', 'address', 'position', 'status', 'provider', 'photo'];

        foreach ($fieldsToCheck as $field) {
            $currentValue = $employee->getAttribute($field);
            $newValue = $newData[$field] ?? null;

            if (empty($currentValue)) $currentValue = null;
            if (empty($newValue)) $newValue = null;

            if ($currentValue !== $newValue) {
                $changes[$field] = [
                    'from' => $currentValue,
                    'to' => $newValue
                ];
            }
        }

        return $changes;
    }

    /**
     * Prepare employee data for comparison
     */
    protected function prepareEmployeeDataForComparison(array $firestoreUser): array
    {
        return [
            'name' => $firestoreUser['name'] ?? '',
            'username' => $firestoreUser['username'] ?? '',
            'email' => $firestoreUser['email'] ?? '',
            'phone' => $firestoreUser['phone'] ?? '',
            'address' => $firestoreUser['address'] ?? '',
            'position' => $firestoreUser['position'] ?? '',
            'status' => $firestoreUser['status'] ?? '',
            'provider' => $firestoreUser['provider'] ?? '',
            'photo' => $firestoreUser['profilePictureUrl'] ?? '',
            'date_of_birth' => !empty($firestoreUser['dateOfBirth']) ? $firestoreUser['dateOfBirth'] : null,
        ];
    }

    /**
     * Reset sync statistics
     */
    protected function resetStats(): void
    {
        $this->syncStats = [
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'no_change' => 0,
            'errors' => 0,
            'error_details' => [],
            'total_firestore' => 0,
            'total_local' => 0,
            'duration_seconds' => 0
        ];
    }

    /**
     * Update sync statistics
     */
    protected function updateStats(array $result): void
    {
        $this->syncStats['synced']++;
        
        switch ($result['action']) {
            case 'created':
                $this->syncStats['created']++;
                break;
            case 'updated':
                $this->syncStats['updated']++;
                break;
            case 'no_change':
                $this->syncStats['no_change']++;
                break;
        }
    }

    /**
     * Get sync result with statistics
     */
    protected function getSyncResult(): array
    {
        // Cache last sync time
        Cache::put('last_manual_sync_time', now(), now()->addHours(24));
        
        return [
            'synced' => $this->syncStats['synced'],
            'created' => $this->syncStats['created'],
            'updated' => $this->syncStats['updated'],
            'no_change' => $this->syncStats['no_change'],
            'errors' => $this->syncStats['error_details'],
            'error_count' => $this->syncStats['errors'],
            'duration_seconds' => $this->syncStats['duration_seconds'],
            'stats' => $this->syncStats
        ];
    }
}
