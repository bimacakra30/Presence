<?php

namespace App\Services;

use App\Models\Permit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ActivePermitSyncService
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
            Log::info('Active permit sync: Already running');
            return ['status' => 'already_running'];
        }

        $this->isRunning = true;
        Cache::put('active_permit_sync_running', true, now()->addHours(24));
        
        Log::info('Active permit sync: Starting continuous sync process');
        
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
        Cache::forget('active_permit_sync_running');
        
        Log::info('Active permit sync: Stopped continuous sync process');
        
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
                Log::error('Active permit sync: Error in sync loop: ' . $e->getMessage());
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
            Log::info('Active permit sync: Starting sync cycle');
            
            // Get all permits from MySQL
            $mysqlPermits = Permit::select('id', 'firestore_id', 'jenis_perizinan', 'nama_karyawan', 'tanggal_mulai', 'tanggal_selesai', 'deskripsi', 'status', 'updated_at')
                ->get()
                ->keyBy('firestore_id');
            
            // Get all permits from Firestore - note: getPerizinan returns ['data' => [...], 'lastDocument' => ...]
            $firestoreResponse = $this->firestoreService->getPerizinan(1000);
            $firestorePermits = $firestoreResponse['data'] ?? [];
            
            $changesDetected = 0;
            $permitsUpdated = 0;
            $permitsCreated = 0;
            
            foreach ($firestorePermits as $firestorePermit) {
                $firestoreId = $firestorePermit['firestore_id'] ?? null;
                if (!$firestoreId) {
                    Log::warning('Active permit sync: Skip permit without firestore_id', ['permit' => $firestorePermit]);
                    continue;
                }
                
                $mysqlPermit = $mysqlPermits->get($firestoreId);
                
                if ($mysqlPermit) {
                    // Check if there are changes
                    if ($this->hasChanges($mysqlPermit, $firestorePermit)) {
                        $changesDetected++;
                        
                        // Update MySQL record
                        $updateData = $this->mapFirestoreToMySQL($firestorePermit);
                        $mysqlPermit->update($updateData);
                        
                        $permitsUpdated++;
                        
                        Log::info('Active permit sync: Permit updated', [
                            'firestore_id' => $firestoreId,
                            'type' => $firestorePermit['type'],
                            'changes' => $this->getChangeDetails($mysqlPermit, $firestorePermit)
                        ]);
                    }
                } else {
                    // Create new permit in MySQL
                    $changesDetected++;
                    
                    $createData = $this->mapFirestoreToMySQL($firestorePermit);
                    $createData['firestore_id'] = $firestoreId;
                    
                    Permit::create($createData);
                    $permitsCreated++;
                    
                    Log::info('Active permit sync: New permit created', [
                        'firestore_id' => $firestoreId,
                        'type' => $firestorePermit['type']
                    ]);
                }
            }
            
            $this->lastSyncTime = now();
            Cache::put('last_active_permit_sync_time', $this->lastSyncTime, now()->addHours(24));
            
            Log::info('Active permit sync: Sync cycle completed', [
                'changes_detected' => $changesDetected,
                'permits_updated' => $permitsUpdated,
                'permits_created' => $permitsCreated,
                'total_firestore' => count($firestorePermits),
                'total_mysql' => $mysqlPermits->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Active permit sync: Error during sync: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if there are changes between MySQL and Firestore
     */
    protected function hasChanges($mysqlPermit, $firestorePermit)
    {
        $fieldMappings = [
            'jenis_perizinan' => 'permitType',      // Firestore: permitType
            'nama_karyawan' => 'employeeName',       // Firestore: employeeName
            'tanggal_mulai' => 'startDate',          // Firestore: startDate
            'tanggal_selesai' => 'endDate',          // Firestore: endDate
            'deskripsi' => 'description',             // Firestore: description
            'status' => 'status'                      // Firestore: status
        ];
        
        foreach ($fieldMappings as $mysqlField => $firestoreField) {
            $mysqlValue = $mysqlPermit->{$mysqlField};
            $firestoreValue = $firestorePermit[$firestoreField] ?? null;
            
            // Handle date fields
            if (in_array($mysqlField, ['tanggal_mulai', 'tanggal_selesai'])) {
                if ($mysqlValue && $firestoreValue) {
                    $mysqlDate = \Carbon\Carbon::parse($mysqlValue)->format('Y-m-d');
                    $firestoreDate = \Carbon\Carbon::parse($firestoreValue)->format('Y-m-d');
                    if ($mysqlDate !== $firestoreDate) {
                        return true;
                    }
                } elseif ($mysqlValue !== $firestoreValue) {
                    return true;
                }
                continue;
            }
            
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
    protected function getChangeDetails($mysqlPermit, $firestorePermit)
    {
        $changes = [];
        $fieldMappings = [
            'jenis_perizinan' => 'permitType',      // Firestore: permitType
            'nama_karyawan' => 'employeeName',       // Firestore: employeeName
            'tanggal_mulai' => 'startDate',          // Firestore: startDate
            'tanggal_selesai' => 'endDate',          // Firestore: endDate
            'deskripsi' => 'description',             // Firestore: description
            'status' => 'status'                      // Firestore: status
        ];
        
        foreach ($fieldMappings as $mysqlField => $firestoreField) {
            $mysqlValue = $mysqlPermit->{$mysqlField};
            $firestoreValue = $firestorePermit[$firestoreField] ?? null;
            
            if ($mysqlField === 'tanggal_mulai' || $mysqlField === 'tanggal_selesai') {
                if ($mysqlValue && $firestoreValue) {
                    $mysqlDate = \Carbon\Carbon::parse($mysqlValue)->format('Y-m-d');
                    $firestoreDate = \Carbon\Carbon::parse($firestoreValue)->format('Y-m-d');
                    if ($mysqlDate !== $firestoreDate) {
                        $changes[$mysqlField] = [
                            'from' => $mysqlDate,
                            'to' => $firestoreDate
                        ];
                    }
                } elseif ($mysqlValue !== $firestoreValue) {
                    $changes[$mysqlField] = [
                        'from' => $mysqlValue,
                        'to' => $firestoreValue
                    ];
                }
            } else {
                if ($mysqlValue !== $firestoreValue) {
                    $changes[$mysqlField] = [
                        'from' => $mysqlValue,
                        'to' => $firestoreValue
                    ];
                }
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
            'jenis_perizinan' => $firestoreData['permitType'] ?? null,
            'nama_karyawan' => $firestoreData['employeeName'] ?? null,
            'tanggal_mulai' => !empty($firestoreData['startDate']) ? $firestoreData['startDate'] : null,
            'tanggal_selesai' => !empty($firestoreData['endDate']) ? $firestoreData['endDate'] : null,
            'deskripsi' => $firestoreData['description'] ?? null,
            'status' => $firestoreData['status'] ?? null,
            'uid' => $firestoreData['uid'] ?? null,
        ];
    }

    /**
     * Get sync status
     */
    public function getSyncStatus()
    {
        return [
            'is_running' => $this->isRunning,
            'cache_status' => Cache::has('active_permit_sync_running'),
            'last_sync_time' => $this->lastSyncTime ? $this->lastSyncTime->toISOString() : null,
            'cache_last_sync' => Cache::get('last_active_permit_sync_time'),
            'sync_interval' => $this->syncInterval . ' seconds'
        ];
    }

    /**
     * Force immediate sync
     */
    public function forceSync()
    {
        try {
            Log::info('Active permit sync: Force sync requested');
            $this->performSync();
            return ['status' => 'success', 'message' => 'Force sync completed'];
        } catch (\Exception $e) {
            Log::error('Active permit sync: Force sync failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if sync is healthy
     */
    public function healthCheck()
    {
        $lastSync = Cache::get('last_active_permit_sync_time');
        $isRunning = Cache::has('active_permit_sync_running');
        
        if (!$isRunning) {
            return [
                'status' => 'stopped',
                'message' => 'Permit sync service is not running'
            ];
        }
        
        if (!$lastSync) {
            return [
                'status' => 'warning',
                'message' => 'Permit sync service running but no sync completed yet'
            ];
        }
        
        $lastSyncTime = \Carbon\Carbon::parse($lastSync);
        $timeSinceLastSync = now()->diffInSeconds($lastSyncTime);
        
        if ($timeSinceLastSync > ($this->syncInterval * 3)) {
            return [
                'status' => 'warning',
                'message' => 'Last permit sync was ' . $timeSinceLastSync . ' seconds ago'
            ];
        }
        
        return [
            'status' => 'healthy',
            'message' => 'Permit sync service is running and healthy'
        ];
    }
}
