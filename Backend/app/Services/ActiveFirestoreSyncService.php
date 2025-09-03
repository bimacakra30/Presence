<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ActiveFirestoreSyncService
{
    protected $firestoreService;
    protected $syncInterval = 30; // 30 detik
    protected $isRunning = false;
    protected $lastSyncTime = null;

    public function __construct(FirestoreService $firestoreService)
    {
        $this->firestoreService = $firestoreService;
    }

    /**
     * Start continuous sync process
     */
    public function startContinuousSync()
    {
        if ($this->isRunning) {
            Log::info('Active sync: Already running');
            return ['status' => 'already_running'];
        }

        $this->isRunning = true;
        Cache::put('active_firestore_sync_running', true, now()->addHours(24));
        
        Log::info('Active sync: Starting continuous sync process');
        
        // Start the sync loop
        $this->syncLoop();
        
        return ['status' => 'started'];
    }

    /**
     * Stop continuous sync process
     */
    public function stopContinuousSync()
    {
        $this->isRunning = false;
        Cache::forget('active_firestore_sync_running');
        
        Log::info('Active sync: Stopped continuous sync process');
        
        return ['status' => 'stopped'];
    }

    /**
     * Main sync loop
     */
    protected function syncLoop()
    {
        while ($this->isRunning) {
            try {
                $this->performSync();
                sleep($this->syncInterval);
            } catch (\Exception $e) {
                Log::error('Active sync: Error in sync loop: ' . $e->getMessage());
                sleep(10); // Wait 10 seconds on error
            }
        }
    }

    /**
     * Perform actual sync
     */
    protected function performSync()
    {
        try {
            Log::info('Active sync: Starting sync cycle');
            
            // Get all employees from MySQL
            $mysqlEmployees = Employee::select('id', 'uid', 'name', 'email', 'updated_at')
                ->get()
                ->keyBy('uid');
            
            // Get all employees from Firestore
            $firestoreEmployees = $this->firestoreService->getUsersMinimal(false); // No cache
            
            $changesDetected = 0;
            $employeesUpdated = 0;
            
            foreach ($firestoreEmployees as $firestoreEmp) {
                $uid = $firestoreEmp['uid'];
                $mysqlEmp = $mysqlEmployees->get($uid);
                
                if ($mysqlEmp) {
                    // Check if there are changes
                    if ($this->hasChanges($mysqlEmp, $firestoreEmp)) {
                        $changesDetected++;
                        
                        // Update MySQL record
                        $updateData = $this->mapFirestoreToMySQL($firestoreEmp);
                        $mysqlEmp->update($updateData);
                        
                        $employeesUpdated++;
                        
                        Log::info('Active sync: Employee updated', [
                            'uid' => $uid,
                            'name' => $firestoreEmp['name'],
                            'changes' => $this->getChangeDetails($mysqlEmp, $firestoreEmp)
                        ]);
                    }
                }
            }
            
            $this->lastSyncTime = now();
            Cache::put('last_active_sync_time', $this->lastSyncTime, now()->addHours(24));
            
            Log::info('Active sync: Sync cycle completed', [
                'changes_detected' => $changesDetected,
                'employees_updated' => $employeesUpdated,
                'total_firestore' => count($firestoreEmployees),
                'total_mysql' => $mysqlEmployees->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Active sync: Error during sync: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if there are changes between MySQL and Firestore
     */
    protected function hasChanges($mysqlEmp, $firestoreEmp)
    {
        $fieldsToCheck = ['name', 'email', 'phone', 'address', 'status', 'position'];
        
        foreach ($fieldsToCheck as $field) {
            $mysqlValue = $mysqlEmp->{$field};
            $firestoreValue = $firestoreEmp[$field] ?? null;
            
            // Handle empty string vs null
            if (($mysqlValue === null || $mysqlValue === '') && ($firestoreValue === null || $firestoreValue === '')) {
                continue;
            }
            
            if ($mysqlValue !== $firestoreValue) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get detailed change information
     */
    protected function getChangeDetails($mysqlEmp, $firestoreEmp)
    {
        $changes = [];
        $fieldsToCheck = ['name', 'email', 'phone', 'address', 'status', 'position'];
        
        foreach ($fieldsToCheck as $field) {
            $mysqlValue = $mysqlEmp->{$field};
            $firestoreValue = $firestoreEmp[$field] ?? null;
            
            if ($mysqlValue !== $firestoreValue) {
                $changes[$field] = [
                    'from' => $mysqlValue,
                    'to' => $firestoreValue
                ];
            }
        }
        
        return $changes;
    }

    /**
     * Map Firestore data to MySQL format
     */
    protected function mapFirestoreToMySQL($firestoreData)
    {
        return [
            'name' => $firestoreData['name'] ?? null,
            'email' => $firestoreData['email'] ?? null,
            'phone' => $firestoreData['phone'] ?? null,
            'address' => $firestoreData['address'] ?? null,
            'status' => $firestoreData['status'] ?? null,
            'position' => $firestoreData['position'] ?? null,
            'profile_picture_url' => $firestoreData['profilePictureUrl'] ?? null,
            'date_of_birth' => !empty($firestoreData['dateOfBirth']) ? $firestoreData['dateOfBirth'] : null,
        ];
    }

    /**
     * Get sync status
     */
    public function getSyncStatus()
    {
        return [
            'is_running' => $this->isRunning,
            'cache_status' => Cache::has('active_firestore_sync_running'),
            'last_sync_time' => $this->lastSyncTime ? $this->lastSyncTime->toISOString() : null,
            'cache_last_sync' => Cache::get('last_active_sync_time'),
            'sync_interval' => $this->syncInterval . ' seconds'
        ];
    }

    /**
     * Force immediate sync
     */
    public function forceSync()
    {
        try {
            Log::info('Active sync: Force sync requested');
            $this->performSync();
            return ['status' => 'success', 'message' => 'Force sync completed'];
        } catch (\Exception $e) {
            Log::error('Active sync: Force sync failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if sync is healthy
     */
    public function healthCheck()
    {
        $lastSync = Cache::get('last_active_sync_time');
        $isRunning = Cache::has('active_firestore_sync_running');
        
        if (!$isRunning) {
            return [
                'status' => 'stopped',
                'message' => 'Sync service is not running'
            ];
        }
        
        if (!$lastSync) {
            return [
                'status' => 'warning',
                'message' => 'Sync service running but no sync completed yet'
            ];
        }
        
        $lastSyncTime = \Carbon\Carbon::parse($lastSync);
        $timeSinceLastSync = now()->diffInSeconds($lastSyncTime);
        
        if ($timeSinceLastSync > ($this->syncInterval * 3)) {
            return [
                'status' => 'warning',
                'message' => 'Last sync was ' . $timeSinceLastSync . ' seconds ago'
            ];
        }
        
        return [
            'status' => 'healthy',
            'message' => 'Sync service is running and healthy'
        ];
    }
}

