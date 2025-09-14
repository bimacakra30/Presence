<?php

namespace App\Services;

use App\Models\Presence;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PresenceSyncService
{
    private $firestoreService;
    protected array $syncStats = [];
    
    public function __construct(FirestoreService $firestoreService)
    {
        $this->firestoreService = $firestoreService;
    }
    
    /**
     * Sinkronisasi seluruh data presence dari Firestore collection 'presence'
     */
    public function syncAllPresenceData(bool $force = false): array
    {
        $this->resetStats();
        $startTime = now();
        
        Log::info('Presence sync: Starting sync all presence data from Firestore', [
            'force' => $force,
            'start_time' => $startTime->toISOString()
        ]);

        try {
            // Ambil semua data dari collection 'presence' di Firestore
            $firestorePresences = $this->getAllPresenceFromFirestore();
            $this->syncStats['total_firestore'] = count($firestorePresences);
            
            Log::info('Presence sync: Retrieved presence data from Firestore', [
                'count' => count($firestorePresences)
            ]);

            foreach ($firestorePresences as $firestorePresence) {
                try {
                    $result = $this->syncSinglePresence($firestorePresence, $force);
                    $this->updateStats($result);
                    
                } catch (\Exception $e) {
                    $this->syncStats['errors']++;
                    $this->syncStats['error_details'][] = [
                        'firestore_id' => $firestorePresence['firestore_id'] ?? 'Unknown',
                        'uid' => $firestorePresence['uid'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                    Log::error('Presence sync: Failed to sync presence', [
                        'firestore_id' => $firestorePresence['firestore_id'] ?? 'Unknown',
                        'uid' => $firestorePresence['uid'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);
            $this->syncStats['duration_seconds'] = $duration;

            Log::info('Presence sync: Completed sync all presence data', [
                'duration' => $duration,
                'stats' => $this->syncStats
            ]);

            // Cache last sync time
            Cache::put('last_presence_sync_time', now(), now()->addHours(24));

            return $this->getSyncResult();

        } catch (\Exception $e) {
            Log::error('Presence sync: Failed to sync all presence data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Ambil semua data presence dari Firestore collection 'presence'
     */
    protected function getAllPresenceFromFirestore(): array
    {
        try {
            // Menggunakan FirestoreClient langsung untuk mengakses collection 'presence'
            $db = new \Google\Cloud\Firestore\FirestoreClient([
                'keyFilePath' => base_path('storage/app/firebase/firebase_credentials.json'),
                'transport' => 'rest',
            ]);
            
            $collection = $db->collection('presence');
            $documents = $collection->documents();
            
            $presences = [];
            foreach ($documents as $document) {
                if ($document->exists()) {
                    $data = $document->data();
                    $data['firestore_id'] = $document->id();
                    $presences[] = $data;
                }
            }
            
            Log::info('Presence sync: Retrieved presence documents from Firestore', [
                'count' => count($presences)
            ]);
            
            return $presences;
            
        } catch (\Exception $e) {
            Log::error('Presence sync: Failed to get presence data from Firestore', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sinkronisasi single presence dari Firestore data
     */
    protected function syncSinglePresence(array $firestoreData, bool $force = false): array
    {
        $firestoreId = $firestoreData['firestore_id'] ?? null;
        $uid = $firestoreData['uid'] ?? null;
        
        if (!$firestoreId) {
            throw new \Exception('Firestore ID tidak ditemukan');
        }

        // Cari employee berdasarkan UID
        $employee = null;
        if ($uid) {
            $employee = Employee::where('uid', $uid)->first();
        }
        
        if (!$employee) {
            Log::warning('Presence sync: Employee not found for UID', [
                'uid' => $uid,
                'firestore_id' => $firestoreId
            ]);
            throw new \Exception("Employee tidak ditemukan untuk UID: {$uid}");
        }

        // Cek apakah presence sudah ada
        $existingPresence = Presence::where('firestore_id', $firestoreId)->first();
        
        // Prepare data untuk sync
        $presenceData = $this->preparePresenceData($firestoreData, $employee);
        
        if ($existingPresence) {
            // Update existing presence
            $hasChanges = $this->hasPresenceChanges($existingPresence, $presenceData);
            
            if ($hasChanges || $force) {
                $existingPresence->update($presenceData);
                
                Log::info('Presence sync: Presence updated from Firestore', [
                    'id' => $existingPresence->id,
                    'firestore_id' => $firestoreId,
                    'employee_name' => $employee->name,
                    'force' => $force
                ]);
                
                return ['action' => 'updated', 'presence' => $existingPresence->fresh()];
            }
            
            return ['action' => 'no_change', 'presence' => $existingPresence];
            
        } else {
            // Create new presence
            $presenceData['firestore_id'] = $firestoreId;
            $presence = Presence::create($presenceData);
            
            Log::info('Presence sync: Presence created from Firestore', [
                'id' => $presence->id,
                'firestore_id' => $firestoreId,
                'employee_name' => $employee->name
            ]);
            
            return ['action' => 'created', 'presence' => $presence];
        }
    }

    /**
     * Prepare data presence dari Firestore untuk Laravel model
     */
    protected function preparePresenceData(array $firestoreData, Employee $employee): array
    {
        return [
            'uid' => $firestoreData['uid'] ?? $employee->uid,
            'nama' => $firestoreData['name'] ?? $employee->name,
            'tanggal' => $this->extractDate($firestoreData['date'] ?? now()->format('Y-m-d')),
            'clock_in' => $this->extractDateTime($firestoreData['clockIn'] ?? null),
            'clock_out' => $this->extractDateTime($firestoreData['clockOut'] ?? null),
            'foto_clock_in' => $firestoreData['fotoClockIn'] ?? null,
            'public_id_clock_in' => $firestoreData['fotoClockInPublicId'] ?? null,
            'foto_clock_out' => $firestoreData['fotoClockOut'] ?? null,
            'public_id_clock_out' => $firestoreData['fotoClockOutPublicId'] ?? null,
            'status' => $this->convertStatusToBoolean($firestoreData['late'] ?? false),
            'durasi_keterlambatan' => $this->extractLateDuration($firestoreData),
            'early_clock_out' => $this->convertToBoolean($firestoreData['earlyClockOut'] ?? false),
            'early_clock_out_reason' => $firestoreData['earlyClockOutReason'] ?? null,
            'location_name' => $firestoreData['locationName'] ?? null,
        ];
    }

    /**
     * Extract date dari berbagai format
     */
    protected function extractDate($dateValue): string
    {
        if (empty($dateValue)) {
            return now()->format('Y-m-d');
        }
        
        // Handle ISO datetime format
        if (strpos($dateValue, 'T') !== false) {
            return Carbon::parse($dateValue)->format('Y-m-d');
        }
        
        return $dateValue;
    }

    /**
     * Extract datetime dari berbagai format
     */
    protected function extractDateTime($dateTimeValue): ?string
    {
        if (empty($dateTimeValue)) {
            return null;
        }
        
        // Handle ISO datetime format
        if (strpos($dateTimeValue, 'T') !== false) {
            return Carbon::parse($dateTimeValue)->format('Y-m-d H:i:s');
        }
        
        return $dateTimeValue;
    }

    /**
     * Extract late duration dari berbagai format
     */
    protected function extractLateDuration(array $firestoreData): ?string
    {
        // Check for lateDuration field first
        if (isset($firestoreData['lateDuration'])) {
            return $firestoreData['lateDuration'];
        }
        
        // Fallback to calculated duration
        return $this->calculateLateDuration($firestoreData);
    }

    /**
     * Calculate late duration berdasarkan check-in time
     */
    protected function calculateLateDuration(array $firestoreData): ?int
    {
        $clockIn = $firestoreData['clockIn'] ?? null;
        if (!$clockIn) {
            return null;
        }
        
        // Assuming work starts at 08:00
        $workStartTime = '08:00:00';
        $clockInDateTime = $this->extractDate($firestoreData['date'] ?? now()->format('Y-m-d')) . ' ' . $this->extractTime($clockIn);
        $workStartDateTime = $this->extractDate($firestoreData['date'] ?? now()->format('Y-m-d')) . ' ' . $workStartTime;
        
        $clockInTime = Carbon::parse($clockInDateTime);
        $workStart = Carbon::parse($workStartDateTime);
        
        if ($clockInTime->gt($workStart)) {
            return $clockInTime->diffInMinutes($workStart);
        }
        
        return 0;
    }

    /**
     * Extract time dari datetime
     */
    protected function extractTime($dateTimeValue): ?string
    {
        if (empty($dateTimeValue)) {
            return null;
        }
        
        // Handle ISO datetime format
        if (strpos($dateTimeValue, 'T') !== false) {
            return Carbon::parse($dateTimeValue)->format('H:i:s');
        }
        
        return $dateTimeValue;
    }

    /**
     * Convert status string to boolean
     */
    protected function convertStatusToBoolean($status): bool
    {
        if (is_bool($status)) {
            return $status;
        }
        
        $status = strtolower(trim($status));
        
        // Return true for late statuses, false for on time
        return in_array($status, ['late', 'terlambat', '1', 'true', 'yes']);
    }

    /**
     * Convert any value to boolean
     */
    protected function convertToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            return strtolower($value) === 'true' || $value === '1';
        }
        
        return (bool) $value;
    }

    /**
     * Check if there are presence data changes
     */
    protected function hasPresenceChanges(Presence $presence, array $newData): bool
    {
        $fieldsToCheck = [
            'uid', 'nama', 'tanggal', 'clock_in', 'clock_out', 
            'foto_clock_in', 'public_id_clock_in', 'foto_clock_out', 
            'public_id_clock_out', 'status', 'durasi_keterlambatan',
            'early_clock_out', 'early_clock_out_reason', 'location_name'
        ];

        foreach ($fieldsToCheck as $field) {
            $currentValue = $presence->getAttribute($field);
            $newValue = $newData[$field] ?? null;

            // Normalize null values
            if (empty($currentValue)) $currentValue = null;
            if (empty($newValue)) $newValue = null;

            if ($currentValue !== $newValue) {
                Log::debug('Presence sync: Field changed', [
                    'field' => $field,
                    'from' => $currentValue,
                    'to' => $newValue
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Get sync status dan statistics
     */
    public function getSyncStatus(): array
    {
        try {
            $localCount = Presence::count();
            $firestorePresences = $this->getAllPresenceFromFirestore();
            $firestoreCount = count($firestorePresences);

            // Get presences with firestore_id
            $syncedCount = Presence::whereNotNull('firestore_id')->count();
            $unsyncedCount = $localCount - $syncedCount;

            // Get recent sync activity (last 24 hours)
            $recentActivity = Presence::where('updated_at', '>=', now()->subDay())
                ->count();

            // Get last sync time from cache
            $lastSyncTime = Cache::get('last_presence_sync_time');

            return [
                'local_presences' => $localCount,
                'firestore_presences' => $firestoreCount,
                'synced_presences' => $syncedCount,
                'unsynced_presences' => $unsyncedCount,
                'recent_activity_24h' => $recentActivity,
                'sync_percentage' => $localCount > 0 ? round(($syncedCount / $localCount) * 100, 2) : 0,
                'last_sync_time' => $lastSyncTime,
                'cache_status' => Cache::has('last_presence_sync_time')
            ];

        } catch (\Exception $e) {
            Log::error('Presence sync: Failed to get sync status', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
     * Hapus data presence dari Firestore
     */
    public function deletePresenceFromFirestore(string $firestoreId): bool
    {
        try {
            Log::info('Presence sync: Deleting presence from Firestore', [
                'firestore_id' => $firestoreId
            ]);

            // Menggunakan FirestoreClient langsung untuk mengakses collection 'presence'
            $db = new \Google\Cloud\Firestore\FirestoreClient([
                'keyFilePath' => base_path('storage/app/firebase/firebase_credentials.json'),
                'transport' => 'rest',
            ]);
            
            $collection = $db->collection('presence');
            $document = $collection->document($firestoreId);
            
            // Check if document exists before deleting
            $snapshot = $document->snapshot();
            if (!$snapshot->exists()) {
                Log::warning('Presence sync: Document does not exist in Firestore', [
                    'firestore_id' => $firestoreId
                ]);
                return false;
            }
            
            $document->delete();
            Log::info('Presence sync: Successfully deleted presence from Firestore', [
                'firestore_id' => $firestoreId
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Presence sync: Failed to delete presence from Firestore', [
                'firestore_id' => $firestoreId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get sync result dengan statistics
     */
    protected function getSyncResult(): array
    {
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
