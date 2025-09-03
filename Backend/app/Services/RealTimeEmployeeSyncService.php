<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RealTimeEmployeeSyncService
{
    protected $firestoreService;
    protected $lastSyncTime;
    protected $syncInterval = 300; // 5 menit (fallback)

    public function __construct(FirestoreService $firestoreService)
    {
        $this->firestoreService = $firestoreService;
        $this->lastSyncTime = Cache::get('last_employee_sync', 0);
    }

    /**
     * Start real-time sync process (Event-driven)
     */
    public function startRealTimeSync()
    {
        try {
            Log::info('Real-time sync: Starting event-driven employee sync process');

            // Get employees from Firestore with minimal data
            $firestoreEmployees = $this->getFirestoreEmployeesMinimal();
            
            if (empty($firestoreEmployees)) {
                Log::warning('Real-time sync: No employees found in Firestore');
                return ['status' => 'no_data', 'message' => 'No employees in Firestore'];
            }

            $syncResults = $this->syncEmployeesEfficiently($firestoreEmployees);
            
            // Update last sync time
            $this->updateLastSyncTime();
            
            Log::info('Real-time sync: Completed', $syncResults);
            
            return [
                'status' => 'success',
                'results' => $syncResults,
                'timestamp' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Real-time sync: Error occurred', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Event-driven sync triggered by Firestore changes
     */
    public function syncOnFirestoreChange($changeType, $documentId, $documentData = null)
    {
        try {
            Log::info('Event-driven sync: Firestore change detected', [
                'change_type' => $changeType,
                'document_id' => $documentId,
                'has_data' => !empty($documentData)
            ]);

            switch ($changeType) {
                case 'CREATE':
                    return $this->handleEmployeeCreate($documentId, $documentData);
                
                case 'UPDATE':
                    return $this->handleEmployeeUpdate($documentId, $documentData);
                
                case 'DELETE':
                    return $this->handleEmployeeDelete($documentId);
                
                default:
                    Log::warning('Event-driven sync: Unknown change type', ['type' => $changeType]);
                    return ['status' => 'unknown_change_type', 'type' => $changeType];
            }

        } catch (\Exception $e) {
            Log::error('Event-driven sync: Error handling change', [
                'change_type' => $changeType,
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle employee creation in Firestore
     */
    protected function handleEmployeeCreate($documentId, $documentData)
    {
        try {
            // Check if employee already exists in MySQL
            $existingEmployee = Employee::where('uid', $documentId)->first();
            
            if ($existingEmployee) {
                Log::info('Event-driven sync: Employee already exists, updating instead', [
                    'uid' => $documentId
                ]);
                return $this->handleEmployeeUpdate($documentId, $documentData);
            }

            // Create new employee in MySQL
            $employeeData = $this->mapFirestoreToMySQL($documentData);
            $employeeData['uid'] = $documentId;
            $employeeData['firestore_id'] = $documentId;
            
            $employee = Employee::create($employeeData);
            
            Log::info('Event-driven sync: Employee created in MySQL', [
                'uid' => $documentId,
                'mysql_id' => $employee->id
            ]);

            return [
                'status' => 'created',
                'employee' => $employee,
                'action' => 'Employee created from Firestore change'
            ];

        } catch (\Exception $e) {
            Log::error('Event-driven sync: Failed to create employee', [
                'uid' => $documentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle employee update in Firestore
     */
    protected function handleEmployeeUpdate($documentId, $documentData)
    {
        try {
            $employee = Employee::where('uid', $documentId)->first();
            
            if (!$employee) {
                Log::warning('Event-driven sync: Employee not found in MySQL, creating instead', [
                    'uid' => $documentId
                ]);
                return $this->handleEmployeeCreate($documentId, $documentData);
            }

            // Check if update is needed
            $needsUpdate = $this->checkIfUpdateNeeded($employee, $documentData);
            
            if (!$needsUpdate) {
                Log::info('Event-driven sync: No update needed', ['uid' => $documentId]);
                return [
                    'status' => 'no_change',
                    'employee' => $employee,
                    'action' => 'No changes detected'
                ];
            }

            // Perform efficient update
            $this->performEfficientUpdate($employee, $documentData);
            
            Log::info('Event-driven sync: Employee updated in MySQL', [
                'uid' => $documentId,
                'mysql_id' => $employee->id
            ]);

            return [
                'status' => 'updated',
                'employee' => $employee,
                'action' => 'Employee updated from Firestore change'
            ];

        } catch (\Exception $e) {
            Log::error('Event-driven sync: Failed to update employee', [
                'uid' => $documentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle employee deletion in Firestore
     */
    protected function handleEmployeeDelete($documentId)
    {
        try {
            $employee = Employee::where('uid', $documentId)->first();
            
            if (!$employee) {
                Log::info('Event-driven sync: Employee not found in MySQL', ['uid' => $documentId]);
                return [
                    'status' => 'not_found',
                    'action' => 'Employee not found in MySQL'
                ];
            }

            // Soft delete or mark as deleted
            $employee->update(['status' => 'non-aktif']);
            
            Log::info('Event-driven sync: Employee marked as inactive in MySQL', [
                'uid' => $documentId,
                'mysql_id' => $employee->id
            ]);

            return [
                'status' => 'deleted',
                'employee' => $employee,
                'action' => 'Employee marked as inactive from Firestore change'
            ];

        } catch (\Exception $e) {
            Log::error('Event-driven sync: Failed to handle employee deletion', [
                'uid' => $documentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Map Firestore data to MySQL structure
     */
    protected function mapFirestoreToMySQL($firestoreData)
    {
        return [
            'name' => $firestoreData['name'] ?? null,
            'email' => $firestoreData['email'] ?? null,
            'phone' => $firestoreData['phone'] ?? null,
            'address' => $firestoreData['address'] ?? null,
            'status' => $firestoreData['status'] ?? 'aktif',
            'position' => $firestoreData['position'] ?? null,
            'photo' => $firestoreData['profilePictureUrl'] ?? null,
            'date_of_birth' => !empty($firestoreData['dateOfBirth']) ? $firestoreData['dateOfBirth'] : null,
            'provider' => $firestoreData['provider'] ?? 'firestore',
            'username' => $firestoreData['username'] ?? null
        ];
    }

    /**
     * Get minimal employee data from Firestore to reduce read operations
     */
    protected function getFirestoreEmployeesMinimal()
    {
        try {
            // Get only essential fields to minimize Firestore reads
            $employees = $this->firestoreService->getUsersMinimal();
            
            Log::info('Real-time sync: Retrieved minimal employee data', [
                'count' => count($employees)
            ]);
            
            return $employees;
            
        } catch (\Exception $e) {
            Log::error('Real-time sync: Failed to get Firestore employees', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sync employees efficiently with minimal database operations
     */
    protected function syncEmployeesEfficiently($firestoreEmployees)
    {
        $stats = [
            'total' => count($firestoreEmployees),
            'updated' => 0,
            'no_changes' => 0,
            'errors' => 0
        ];

        // Use batch processing to reduce database calls
        $batchUpdates = [];
        $batchInserts = [];

        foreach ($firestoreEmployees as $firestoreEmployee) {
            try {
                if (!isset($firestoreEmployee['uid'])) {
                    continue;
                }

                $result = $this->processEmployeeUpdate($firestoreEmployee);
                
                if ($result['action'] === 'updated') {
                    $stats['updated']++;
                } elseif ($result['action'] === 'no_change') {
                    $stats['no_changes']++;
                }

            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('Real-time sync: Employee sync error', [
                    'uid' => $firestoreEmployee['uid'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $stats;
    }

    /**
     * Process individual employee update efficiently
     */
    protected function processEmployeeUpdate($firestoreEmployee)
    {
        $uid = $firestoreEmployee['uid'];
        
        // Check if employee exists in MySQL
        $employee = Employee::where('uid', $uid)->first();
        
        if (!$employee) {
            // Employee doesn't exist - skip (as per requirement)
            return ['action' => 'not_found', 'uid' => $uid];
        }

        // Check if update is needed by comparing essential fields
        $needsUpdate = $this->checkIfUpdateNeeded($employee, $firestoreEmployee);
        
        if (!$needsUpdate) {
            return ['action' => 'no_change', 'employee' => $employee];
        }

        // Perform efficient update
        $this->performEfficientUpdate($employee, $firestoreEmployee);
        
        return ['action' => 'updated', 'employee' => $employee];
    }

    /**
     * Check if employee needs update by comparing essential fields only
     */
    protected function checkIfUpdateNeeded($employee, $firestoreEmployee)
    {
        $fieldsToCheck = [
            'name' => 'name',
            'email' => 'email',
            'phone' => 'phone',
            'status' => 'status',
            'position' => 'position'
        ];

        foreach ($fieldsToCheck as $firestoreField => $mysqlField) {
            if (isset($firestoreEmployee[$firestoreField])) {
                $firestoreValue = $firestoreEmployee[$firestoreField];
                $mysqlValue = $employee->$mysqlField;
                
                // Handle empty values properly
                if ($firestoreValue === '' && $mysqlValue !== null) {
                    return true;
                }
                
                if ($firestoreValue !== $mysqlValue) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Perform efficient update with minimal database operations
     */
    protected function performEfficientUpdate($employee, $firestoreEmployee)
    {
        $updateData = [];
        
        // Only update fields that have changed
        $fieldMappings = [
            'name' => 'name',
            'email' => 'email',
            'phone' => 'phone',
            'address' => 'address',
            'status' => 'status',
            'position' => 'position',
            'photo' => 'profilePictureUrl'
        ];

        foreach ($fieldMappings as $firestoreField => $mysqlField) {
            if (isset($firestoreEmployee[$firestoreField])) {
                $value = $firestoreEmployee[$firestoreField];
                
                // Handle special cases
                if ($firestoreField === 'profilePictureUrl' && !empty($value)) {
                    $updateData['photo'] = $value;
                } elseif ($firestoreField === 'dateOfBirth') {
                    $updateData['date_of_birth'] = !empty($value) ? $value : null;
                } else {
                    $updateData[$mysqlField] = $value;
                }
            }
        }

        if (!empty($updateData)) {
            // Use updateQuietly to avoid triggering events
            $employee->updateQuietly($updateData);
            
            Log::info('Real-time sync: Employee updated', [
                'uid' => $employee->uid,
                'updated_fields' => array_keys($updateData)
            ]);
        }
    }

    /**
     * Check if sync should be performed based on time interval
     */
    protected function shouldSync()
    {
        $timeSinceLastSync = time() - $this->lastSyncTime;
        return $timeSinceLastSync >= $this->syncInterval;
    }

    /**
     * Update last sync timestamp
     */
    protected function updateLastSyncTime()
    {
        $this->lastSyncTime = time();
        Cache::put('last_employee_sync', $this->lastSyncTime, 3600); // 1 hour
    }

    /**
     * Get sync status and statistics
     */
    public function getSyncStatus()
    {
        return [
            'last_sync' => $this->lastSyncTime ? date('Y-m-d H:i:s', $this->lastSyncTime) : 'Never',
            'next_sync_in' => $this->getNextSyncIn(),
            'sync_interval' => $this->syncInterval . ' seconds',
            'is_sync_due' => $this->shouldSync()
        ];
    }

    /**
     * Get time until next sync
     */
    protected function getNextSyncIn()
    {
        if ($this->lastSyncTime === 0) {
            return 'Now';
        }
        
        $timeUntilNext = $this->syncInterval - (time() - $this->lastSyncTime);
        return $timeUntilNext > 0 ? $timeUntilNext . ' seconds' : 'Now';
    }

    /**
     * Force immediate sync (bypass time check)
     */
    public function forceSync()
    {
        $this->lastSyncTime = 0; // Reset to force sync
        return $this->startRealTimeSync();
    }

    /**
     * Set custom sync interval
     */
    public function setSyncInterval($seconds)
    {
        $this->syncInterval = max(60, $seconds); // Minimum 1 minute
        Log::info('Real-time sync: Interval updated', ['new_interval' => $this->syncInterval]);
    }
}
