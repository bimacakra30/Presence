<?php

namespace App\Services;

use App\Models\Presence;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ActivePresenceSyncService
{
    private $firestoreService;
    
    public function __construct(FirestoreService $firestoreService)
    {
        $this->firestoreService = $firestoreService;
    }
    
    /**
     * Force sync all presences from Firestore to MySQL
     */
    public function forceSync(): array
    {
        $results = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'skipped' => 0
        ];
        
        try {
            Log::info('Starting presence force sync from Firestore');
            
            $collection = $this->firestoreService->getCollection('presences');
            $documents = $collection->documents();
            
            foreach ($documents as $document) {
                if ($document->exists()) {
                    $data = $document->data();
                    
                    // Skip if this is actually employee data (not presence data)
                    if (isset($data['name']) && isset($data['email']) && !isset($data['check_in_time'])) {
                        $results['skipped']++;
                        continue;
                    }
                    
                    try {
                        $result = $this->syncPresenceFromFirestore($data, $document->id());
                        $results[$result['action']]++;
                    } catch (\Exception $e) {
                        Log::error('Error syncing presence: ' . $e->getMessage(), [
                            'document_id' => $document->id(),
                            'data' => $data
                        ]);
                        $results['errors']++;
                    }
                }
            }
            
            Log::info('Presence force sync completed', $results);
            
        } catch (\Exception $e) {
            Log::error('Presence force sync failed: ' . $e->getMessage());
            $results['errors']++;
        }
        
        return $results;
    }
    
    /**
     * Sync single presence from Firestore data
     */
    public function syncPresenceFromFirestore(array $firestoreData, string $firestoreId): array
    {
        $presenceId = $firestoreData['presence_id'] ?? $firestoreId;
        $employeeUid = $firestoreData['employee_uid'] ?? $firestoreData['uid'] ?? '';
        
        // Find employee by UID
        $employee = Employee::where('uid', $employeeUid)->first();
        if (!$employee) {
            throw new \Exception("Employee not found for UID: {$employeeUid}");
        }
        
        // Check if presence already exists
        $existingPresence = Presence::where('firestore_id', $firestoreId)
            ->orWhere(function($query) use ($employeeUid, $presenceId) {
                $query->where('uid', $employeeUid)
                      ->where('tanggal', $this->extractDate($firestoreData['date'] ?? now()->format('Y-m-d')));
            })
            ->first();
        
        $presenceData = [
            'uid' => $employeeUid, // Use employee UID, not presence ID
            'firestore_id' => $firestoreId,
            'nama' => $employee->name, // Use employee name
            'tanggal' => $this->extractDate($firestoreData['date'] ?? now()->format('Y-m-d')),
            'clock_in' => $this->extractTime($firestoreData['clockIn'] ?? $firestoreData['check_in_time'] ?? null),
            'clock_out' => $this->extractTime($firestoreData['clockOut'] ?? $firestoreData['check_out_time'] ?? null),
            'foto_clock_in' => $firestoreData['fotoClockIn'] ?? $firestoreData['foto_clock_in'] ?? null,
            'public_id_clock_in' => $firestoreData['fotoClockInPublicId'] ?? $firestoreData['public_id_clock_in'] ?? null,
            'foto_clock_out' => $firestoreData['fotoClockOut'] ?? $firestoreData['foto_clock_out'] ?? null,
            'public_id_clock_out' => $firestoreData['fotoClockOutPublicId'] ?? $firestoreData['public_id_clock_out'] ?? null,
            'status' => $this->convertStatusToBoolean($firestoreData['status'] ?? 'present'),
            'durasi_keterlambatan' => $this->extractLateDuration($firestoreData),
            // New fields from Firestore
            'early_clock_out' => $this->convertToBoolean($firestoreData['earlyClockOut'] ?? false),
            'early_clock_out_reason' => $firestoreData['earlyClockOutReason'] ?? null,
            'location_name' => $firestoreData['locationName'] ?? null,
        ];
        
        if ($existingPresence) {
            // Update existing presence
            $existingPresence->update($presenceData);
            Log::info("Presence updated: {$presenceId} for employee {$employee->name}");
            return ['action' => 'updated', 'presence' => $existingPresence];
        } else {
            // Create new presence
            $presence = Presence::create($presenceData);
            Log::info("Presence created: {$presenceId} for employee {$employee->name}");
            return ['action' => 'created', 'presence' => $presence];
        }
    }
    
    /**
     * Extract date from various formats
     */
    private function extractDate($dateValue): string
    {
        if (empty($dateValue)) {
            return now()->format('Y-m-d');
        }
        
        // Handle ISO datetime format
        if (strpos($dateValue, 'T') !== false) {
            return \Carbon\Carbon::parse($dateValue)->format('Y-m-d');
        }
        
        return $dateValue;
    }
    
    /**
     * Extract time from various formats
     */
    private function extractTime($timeValue): ?string
    {
        if (empty($timeValue)) {
            return null;
        }
        
        // Handle ISO datetime format
        if (strpos($timeValue, 'T') !== false) {
            return \Carbon\Carbon::parse($timeValue)->format('H:i:s');
        }
        
        return $timeValue;
    }
    
    /**
     * Extract late duration from various formats
     */
    private function extractLateDuration(array $firestoreData): ?string
    {
        // Check for lateDuration field first (from mobile app)
        if (isset($firestoreData['lateDuration'])) {
            return $firestoreData['lateDuration'];
        }
        
        // Fallback to calculated duration
        return $this->calculateLateDuration($firestoreData);
    }
    
    /**
     * Convert status string to boolean
     */
    private function convertStatusToBoolean($status): bool
    {
        if (is_bool($status)) {
            return $status;
        }
        
        $status = strtolower(trim($status));
        
        // Return true for present statuses, false for absent
        return in_array($status, ['present', 'hadir', '1', 'true', 'yes']);
    }
    
    /**
     * Convert any value to boolean
     */
    private function convertToBoolean($value): bool
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
     * Calculate late duration based on check-in time
     */
    private function calculateLateDuration(array $firestoreData): ?int
    {
        $checkInTime = $firestoreData['check_in_time'] ?? null;
        if (!$checkInTime) {
            return null;
        }
        
        // Assuming work starts at 08:00
        $workStartTime = '08:00:00';
        $checkInDateTime = now()->format('Y-m-d') . ' ' . $checkInTime;
        $workStartDateTime = now()->format('Y-m-d') . ' ' . $workStartTime;
        
        $checkIn = \Carbon\Carbon::parse($checkInDateTime);
        $workStart = \Carbon\Carbon::parse($workStartDateTime);
        
        if ($checkIn->gt($workStart)) {
            return $checkIn->diffInMinutes($workStart);
        }
        
        return 0;
    }
    
    /**
     * Handle presence change from webhook
     */
    public function handlePresenceChange(string $eventType, string $documentId, array $documentData): array
    {
        try {
            switch ($eventType) {
                case 'CREATE':
                case 'UPDATE':
                    return $this->syncPresenceFromFirestore($documentData, $documentId);
                    
                case 'DELETE':
                    $presence = Presence::where('firestore_id', $documentId)->first();
                    if ($presence) {
                        $presence->delete();
                        Log::info("Presence deleted: {$documentId}");
                        return ['action' => 'deleted', 'presence' => $presence];
                    }
                    break;
            }
            
            return ['action' => 'no_change', 'message' => 'No action needed'];
            
        } catch (\Exception $e) {
            Log::error('Error handling presence change: ' . $e->getMessage(), [
                'event_type' => $eventType,
                'document_id' => $documentId,
                'data' => $documentData
            ]);
            throw $e;
        }
    }
}
