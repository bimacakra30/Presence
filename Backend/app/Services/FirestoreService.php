<?php

namespace App\Services;

use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FirestoreService
{
    protected $db;
    protected $cacheTimeout; // Cache timeout dari environment variable

    public function __construct()
    {
        $this->db = new FirestoreClient([
            'keyFilePath' => base_path('storage/app/firebase/firebase_credentials.json'),
            'transport' => 'rest',
        ]);
        
        // Set cache timeout dari environment variable
        $this->cacheTimeout = (int) env('FIRESTORE_CACHE_TIMEOUT', 300); // Default 5 menit
    }

    public function getCollection()
    {
        return $this->db->collection('employees');
    }

    /**
     * Cache key tracking for non-Redis cache stores
     */
    protected function addToTrackedKeys($key)
    {
        $trackedKeys = Cache::get('firestore_tracked_keys', []);
        if (!in_array($key, $trackedKeys)) {
            $trackedKeys[] = $key;
            Cache::put('firestore_tracked_keys', $trackedKeys, now()->addHours(24));
        }
    }

    /**
     * Create user - compatible dengan existing method signature
     */
    public function createUser($id, $data)
    {
        $collection = $this->db->collection('employees');
        
        if (is_array($id)) {
            // Jika $id adalah data (untuk auto-generate ID)
            $docRef = $collection->add($id);
            $this->clearUsersCache();
            return $docRef->id();
        } else {
            // Jika $id adalah string (untuk specific ID)
            $result = $collection->document((string)$id)->set($data);
            $this->clearUsersCache();
            return $result;
        }
    }

    public function updateUser($id, $data)
    {
        $collection = $this->db->collection('employees');
        $result = $collection->document((string)$id)->set($data, ['merge' => true]);
        
        // Clear cache setelah update
        $this->clearUsersCache();
        
        return $result;
    }

    public function deleteUser($id)
    {
        $collection = $this->db->collection('employees');
        $result = $collection->document((string)$id)->delete();
        
        // Clear cache setelah delete
        $this->clearUsersCache();
        
        return $result;
    }

    /**
     * Get users with caching for better performance
     */
    public function getUsers($useCache = true)
    {
        $cacheKey = 'firestore_employees_list';
        
        // Jika realtime dinonaktifkan, selalu gunakan cache jika tersedia
        $realtimeEnabled = env('FIRESTORE_REALTIME_ENABLED', false);
        if (!$realtimeEnabled && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $collection = $this->db->collection('employees');
        
        // Order by createdAt untuk konsistensi
        $documents = $collection->orderBy('createdAt', 'DESC')->documents();

        $users = [];
        foreach ($documents as $document) {
            if ($document->exists()) {
                $data = $document->data();
                $data['id'] = $document->id();
                
                // Pastikan field yang diperlukan ada
                $data = $this->normalizeUserData($data);
                $users[] = $data;
            }
        }

        // Cache hasil untuk performa yang lebih baik
        if ($useCache) {
            Cache::put($cacheKey, $users, now()->addSeconds($this->cacheTimeout));
        }

        return $users;
    }

    /**
     * Get minimal user data from Firestore to reduce read operations
     * Only fetches essential fields needed for sync comparison
     */
    public function getUsersMinimal($useCache = true)
    {
        $cacheKey = 'firestore_employees_minimal';
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $users = [];
            $collection = $this->db->collection('employees');
            $documents = $collection->orderBy('createdAt', 'DESC')->documents();

            foreach ($documents as $document) {
                if ($document->exists()) {
                    $data = $document->data();
                    $uid = $document->id();
                    
                    // Only fetch essential fields to minimize Firestore reads
                    $minimalData = [
                        'uid' => $uid,
                        'name' => $data['name'] ?? null,
                        'email' => $data['email'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'address' => $data['address'] ?? null,
                        'status' => $data['status'] ?? null,
                        'position' => $data['position'] ?? null,
                        'profilePictureUrl' => $data['profilePictureUrl'] ?? null,
                        'dateOfBirth' => $data['dateOfBirth'] ?? null
                    ];
                    
                    $users[] = $minimalData;
                }
            }

            // Cache minimal data for better performance
            if ($useCache) {
                Cache::put($cacheKey, $users, now()->addSeconds($this->cacheTimeout));
            }

            Log::info('Retrieved minimal user data from Firestore', [
                'count' => count($users),
                'fields_per_user' => count($minimalData)
            ]);

            return $users;
        } catch (\Exception $e) {
            Log::error('Failed to get minimal users from Firestore: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get specific user by ID
     */
    public function getUser($id)
    {
        $cacheKey = "firestore_employee_{$id}";
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $collection = $this->db->collection('employees');
        $document = $collection->document((string)$id);

        try {
            $snapshot = $document->snapshot();
            
            if ($snapshot->exists()) {
                $data = $snapshot->data();
                $data['id'] = $snapshot->id();
                $data = $this->normalizeUserData($data);
                
                // Cache individual user and track the key
                Cache::put($cacheKey, $data, now()->addSeconds($this->cacheTimeout));
                $this->addToTrackedKeys($cacheKey);
                
                return $data;
            }
        } catch (\Exception $e) {
            Log::warning("Failed to get user {$id} from Firestore: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Normalize data dari Firestore untuk konsistensi
     */
    protected function normalizeUserData($data)
    {
        return [
            'id' => $data['id'] ?? null,
            'uid' => $data['uid'] ?? null,
            'name' => $data['name'] ?? '',
            'username' => $data['username'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'dateOfBirth' => $data['dateOfBirth'] ?? null,
            'position' => $data['position'] ?? '',
            'status' => $data['status'] ?? 'aktif',
            'provider' => $data['provider'] ?? 'google',
            'profilePictureUrl' => $data['profilePictureUrl'] ?? null,
            'createdAt' => $data['createdAt'] ?? null,
            'updatedAt' => $data['updatedAt'] ?? null,
        ];
    }

    /**
     * Clear cache untuk force refresh - Fixed version with recursion prevention
     */
    public function clearUsersCache()
    {
        // Clear main caches
        Cache::forget('firestore_employees_list');
        Cache::forget('firestore_employees_count');
        
        // Use a static flag to prevent recursion
        static $isClearing = false;
        if ($isClearing) {
            Log::warning('Prevented recursive call to clearUsersCache');
            return;
        }
        
        $isClearing = true;
        try {
            // Check cache store type
            $cacheStore = Cache::getStore();
            
            if (method_exists($cacheStore, 'getRedis')) {
                // Redis cache - use pattern matching
                $redis = $cacheStore->getRedis();
                $keys = $redis->keys('*firestore_employee_*');
                if ($keys && count($keys) > 0) {
                    $redis->del($keys);
                }
            } else {
                // Non-Redis cache - use tracked keys approach
                $trackedKeys = Cache::get('firestore_tracked_keys', []);
                foreach ($trackedKeys as $key) {
                    Cache::forget($key);
                }
                // Clear the tracking list
                Cache::forget('firestore_tracked_keys');
            }
        } catch (\Exception $e) {
            Log::info('Could not clear individual employee caches: ' . $e->getMessage());
        } finally {
            $isClearing = false;
        }
    }

    /**
     * Get users count untuk navigation badge
     */
    public function getUsersCount()
    {
        $cacheKey = 'firestore_employees_count';
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Check if we're hitting rate limits
            $lastRequestTime = Cache::get('firestore_last_request_time');
            $currentTime = time();
            
            if ($lastRequestTime && ($currentTime - $lastRequestTime) < 1) {
                // Rate limiting: wait at least 1 second between requests
                return Cache::get($cacheKey, 0);
            }
            
            Cache::put('firestore_last_request_time', $currentTime, 60);
            
            $collection = $this->db->collection('employees');
            $documents = $collection->documents();
            
            $count = 0;
            foreach ($documents as $document) {
                if ($document->exists()) {
                    $count++;
                }
            }
            
            Cache::put($cacheKey, $count, now()->addSeconds($this->cacheTimeout));
            return $count;
            
        } catch (\Exception $e) {
            Log::error('Failed to get users count from Firestore: ' . $e->getMessage());
            
            // If quota exceeded, use longer cache
            if (strpos($e->getMessage(), 'Quota exceeded') !== false || 
                strpos($e->getMessage(), 'RESOURCE_EXHAUSTED') !== false) {
                Cache::put($cacheKey, 0, now()->addMinutes(30)); // Cache for 30 minutes on quota error
            }
            
            return 0;
        }
    }

    /**
     * Search users by specific field
     */
    public function searchUsers($field, $value)
    {
        $collection = $this->db->collection('employees');
        $documents = $collection->where($field, '==', $value)->documents();

        $users = [];
        foreach ($documents as $document) {
            if ($document->exists()) {
                $data = $document->data();
                $data['id'] = $document->id();
                $data = $this->normalizeUserData($data);
                $users[] = $data;
            }
        }

        return $users;
    }

    /**
     * Sync single employee from Firestore by UID - Minimal version
     */
    public function syncEmployeeByUid($uid)
    {
        try {
            $firestoreUsers = $this->searchUsers('uid', $uid);
            
            if (empty($firestoreUsers)) {
                throw new \Exception("Employee with UID {$uid} not found in Firestore");
            }

            $firestoreUser = $firestoreUsers[0];
            $employee = \App\Models\Employee::where('uid', $uid)->first();

            if ($employee) {
                // Update existing - hanya field yang bisa diupdate
                $updateData = [];
                if (isset($firestoreUser['name'])) $updateData['name'] = $firestoreUser['name'];
                if (isset($firestoreUser['address'])) $updateData['address'] = $firestoreUser['address'];
                if (isset($firestoreUser['username'])) $updateData['username'] = $firestoreUser['username'];
                if (isset($firestoreUser['phone'])) $updateData['phone'] = $firestoreUser['phone'];
                if (isset($firestoreUser['provider'])) $updateData['provider'] = $firestoreUser['provider'];
                if (isset($firestoreUser['dateOfBirth'])) $updateData['date_of_birth'] = $firestoreUser['dateOfBirth'];
                if (isset($firestoreUser['status'])) $updateData['status'] = $firestoreUser['status'];
                if (isset($firestoreUser['position'])) $updateData['position'] = $firestoreUser['position'];
                if (isset($firestoreUser['profilePictureUrl'])) $updateData['photo'] = $firestoreUser['profilePictureUrl'];
                
                if (!empty($updateData)) {
                    $employee->updateQuietly($updateData);
                    return ['action' => 'updated', 'employee' => $employee];
                }
                
                return ['action' => 'no_change', 'employee' => $employee];
            }

            return ['action' => 'not_found', 'uid' => $uid];
        } catch (\Exception $e) {
            Log::error("Failed to sync employee by UID {$uid}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync all employees from Firestore - Minimal version
     */
    public function syncAllEmployees()
    {
        $firestoreUsers = $this->getUsers(false);
        
        $synced = 0;
        $updated = 0;
        $errors = [];

        foreach ($firestoreUsers as $firestoreUser) {
            try {
                if (!isset($firestoreUser['uid'])) continue;
                
                $result = $this->syncEmployeeByUid($firestoreUser['uid']);
                
                if ($result['action'] === 'updated') {
                    $updated++;
                }
                $synced++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'user' => $firestoreUser['email'] ?? 'Unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'synced' => $synced,
            'updated' => $updated,
            'created' => 0, // Tidak create otomatis di minimal version
            'errors' => $errors
        ];
    }

    // Keep all existing methods for backward compatibility...
    public function getAbsensi()
    {
        $collection = $this->db->collection('presence')->documents();
        $absensi = [];

        foreach ($collection as $document) {
            if ($document->exists()) {
                $data = $document->data();
                $absensi[] = array_merge($data, [
                    'firestore_id' => $document->id(),
                ]);
            }
        }

        return $absensi;
    }

    public function deleteAbsensi($documentId)
    {
        try {
            Log::info("Attempting to delete presence document from Firestore: {$documentId}");
            
            // Menggunakan collection 'presence' langsung
            $collection = $this->db->collection('presence');
            $document = $collection->document($documentId);
            
            // Check if document exists before deleting
            $snapshot = $document->snapshot();
            if (!$snapshot->exists()) {
                Log::warning("Document {$documentId} does not exist in Firestore collection 'presence'");
                return false;
            }
            
            $document->delete();
            Log::info("Successfully deleted presence document from Firestore: {$documentId}");
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete presence document from Firestore: {$documentId}, Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getCollectionPosition()
    {
        return $this->db->collection('employee_positions');
    }

    public function createUserPosition($id, $data)
    {
        $collection = $this->db->collection('employee_positions');
        $collection->document((string)$id)->set($data);
    }

    public function updateUserPosition($id, $data)
    {
        $collection = $this->db->collection('employee_positions');
        $collection->document((string)$id)->set($data, ['merge' => true]);
    }

    public function deleteUserPosition($id)
    {
        $collection = $this->db->collection('employee_positions');
        $collection->document((string)$id)->delete();
    }

    public function getUsersPosition()
    {
        $collection = $this->db->collection('employee_positions');
        $documents = $collection->documents();

        $users = [];
        foreach ($documents as $document) {
            if ($document->exists()) {
                $data = $document->data();
                $data['id'] = $document->id();
                $users[] = $data;
            }
        }

        return $users;
    }

    public function getPerizinan($limit = 100, $lastDocument = null)
    {
        $query = $this->db->collection('permits')->limit($limit);
        
        if ($lastDocument) {
            $query = $query->startAfter($lastDocument);
        }

        $documents = $query->documents();
        $absensi = [];
        $lastDoc = null;

        foreach ($documents as $document) {
            if ($document->exists()) {
                $data = $document->data();
                $absensi[] = array_merge($data, [
                    'firestore_id' => $document->id(),
                ]);
                $lastDoc = $document;
            }
        }

        return [
            'data' => $absensi,
            'lastDocument' => $lastDoc,
        ];
    }

    public function deletePerizinan($documentId)
    {
        $collection = $this->db->collection('permits');
        $collection->document($documentId)->delete();
    }

    public function updatePerizinan($id, $data)
    {
        $collection = $this->db->collection('permits');
        $collection->document((string)$id)->set($data, ['merge' => true]);
    }

    public function getCollectionMaps()
    {
        return $this->db->collection('geo_locator');
    }

    public function createMaps($collectionName, $id, array $data)
    {
        $collection = $this->db->collection($collectionName);
        $collection->document((string)$id)->set($data);
    }

    public function deleteMaps($collectionName, $id)
    {
        $collection = $this->db->collection($collectionName);
        $collection->document((string)$id)->delete();
    }

    public function updateMaps($collectionName, $id, array $data)
    {
        $collection = $this->db->collection($collectionName);
        $collection->document((string)$id)->set($data, ['merge' => true]);
    }

    public function getMaps()
    {
        $collection = $this->db->collection('geo_locator');
        $documents = $collection->documents();

        $users = [];
        foreach ($documents as $document) {
            if ($document->exists()) {
                $data = $document->data();
                $data['id'] = $document->id();
                $users[] = $data;
            }
        }

        return $users;
    }

    /**
     * Get FCM tokens for specific employee from Firestore
     */
    public function getEmployeeFcmTokens($employeeUid)
    {
        try {
            // Try collection 'fcmTokens' first (as separate collection)
            $fcmTokensCollection = $this->db->collection('fcmTokens');
            $tokens = $fcmTokensCollection->where('employeeUid', '=', $employeeUid)->documents();

            $fcmTokens = [];
            $deviceTokens = []; // Track tokens by device to prevent duplicates
            
            foreach ($tokens as $token) {
                if ($token->exists()) {
                    $tokenData = $token->data();
                    $deviceId = $tokenData['deviceId'] ?? 'unknown';
                    
                    // Only add if we haven't seen this device before, or if it's a newer token
                    if (!isset($deviceTokens[$deviceId]) || 
                        ($tokenData['lastUsed'] ?? '') > ($deviceTokens[$deviceId]['lastUsed'] ?? '')) {
                        
                        $deviceTokens[$deviceId] = [
                            'id' => $token->id(),
                            'token' => $tokenData['token'] ?? '',
                            'device_id' => $deviceId,
                            'platform' => $tokenData['platform'] ?? 'unknown',
                            'created_at' => $tokenData['createdAt'] ?? null,
                            'last_used' => $tokenData['lastUsed'] ?? null,
                        ];
                    }
                }
            }

            // If no tokens found in separate collection, try subcollection approach
            if (empty($deviceTokens)) {
                $collection = $this->db->collection('employees');
                $document = $collection->document($employeeUid);
                
                try {
                    $snapshot = $document->snapshot();
                    if (!$snapshot->exists()) {
                        return [];
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to check document existence for employee {$employeeUid}: " . $e->getMessage());
                    return [];
                }

                // Get fcmTokens subcollection
                $fcmTokensSubcollection = $document->collection('fcmTokens');
                $subTokens = $fcmTokensSubcollection->documents();

                foreach ($subTokens as $token) {
                    if ($token->exists()) {
                        $tokenData = $token->data();
                        $deviceId = $tokenData['deviceId'] ?? 'unknown';
                        
                        // Only add if we haven't seen this device before, or if it's a newer token
                        if (!isset($deviceTokens[$deviceId]) || 
                            ($tokenData['lastUsed'] ?? '') > ($deviceTokens[$deviceId]['lastUsed'] ?? '')) {
                            
                            $deviceTokens[$deviceId] = [
                                'id' => $token->id(),
                                'token' => $tokenData['token'] ?? '',
                                'device_id' => $deviceId,
                                'platform' => $tokenData['platform'] ?? 'unknown',
                                'created_at' => $tokenData['createdAt'] ?? null,
                                'last_used' => $tokenData['lastUsed'] ?? null,
                            ];
                        }
                    }
                }
            }

            // Convert device tokens back to array
            $fcmTokens = array_values($deviceTokens);
            
            Log::info("Retrieved " . count($fcmTokens) . " unique device tokens for employee {$employeeUid}");
            
            return $fcmTokens;
        } catch (\Exception $e) {
            Log::error("Failed to get FCM tokens for employee {$employeeUid}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add FCM token to employee in Firestore
     */
    public function addEmployeeFcmToken($employeeUid, $fcmToken, $deviceId = null, $platform = 'unknown')
    {
        try {
            $collection = $this->db->collection('employees');
            $document = $collection->document($employeeUid);
            
            try {
                $snapshot = $document->snapshot();
                if (!$snapshot->exists()) {
                    throw new \Exception("Employee with UID {$employeeUid} not found in Firestore");
                }
            } catch (\Exception $e) {
                Log::warning("Failed to check document existence for employee {$employeeUid}: " . $e->getMessage());
                throw new \Exception("Employee with UID {$employeeUid} not found in Firestore");
            }

            // Add to fcmTokens subcollection
            $fcmTokensCollection = $document->collection('fcmTokens');
            
            $tokenData = [
                'token' => $fcmToken,
                'deviceId' => $deviceId,
                'platform' => $platform,
                'createdAt' => now()->toISOString(),
                'lastUsed' => now()->toISOString(),
            ];

            $docRef = $fcmTokensCollection->add($tokenData);
            
            Log::info("FCM token added for employee {$employeeUid}: {$docRef->id()}");
            
            return $docRef->id();
        } catch (\Exception $e) {
            Log::error("Failed to add FCM token for employee {$employeeUid}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Remove FCM token from employee in Firestore
     */
    public function removeEmployeeFcmToken($employeeUid, $tokenId)
    {
        try {
            $collection = $this->db->collection('employees');
            $document = $collection->document($employeeUid);
            
            try {
                $snapshot = $document->snapshot();
                if (!$snapshot->exists()) {
                    throw new \Exception("Employee with UID {$employeeUid} not found in Firestore");
                }
            } catch (\Exception $e) {
                Log::warning("Failed to check document existence for employee {$employeeUid}: " . $e->getMessage());
                throw new \Exception("Employee with UID {$employeeUid} not found in Firestore");
            }

            // Remove from fcmTokens subcollection
            $fcmTokensCollection = $document->collection('fcmTokens');
            $fcmTokensCollection->document($tokenId)->delete();
            
            Log::info("FCM token removed for employee {$employeeUid}: {$tokenId}");
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to remove FCM token for employee {$employeeUid}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update FCM token last used timestamp
     */
    public function updateFcmTokenLastUsed($employeeUid, $tokenId)
    {
        try {
            $collection = $this->db->collection('employees');
            $document = $collection->document($employeeUid);
            
            try {
                $snapshot = $document->snapshot();
                if (!$snapshot->exists()) {
                    throw new \Exception("Employee with UID {$employeeUid} not found in Firestore");
                }
            } catch (\Exception $e) {
                Log::warning("Failed to check document existence for employee {$employeeUid}: " . $e->getMessage());
                throw new \Exception("Employee with UID {$employeeUid} not found in Firestore");
            }

            // Update lastUsed timestamp
            $fcmTokensCollection = $document->collection('fcmTokens');
            $fcmTokensCollection->document($tokenId)->update([
                ['path' => 'lastUsed', 'value' => now()->toISOString()]
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update FCM token last used for employee {$employeeUid}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all active FCM tokens for all employees
     */
    public function getAllActiveFcmTokens()
    {
        try {
            $collection = $this->db->collection('employees');
            $documents = $collection->documents();

            $allTokens = [];
            foreach ($documents as $document) {
                if ($document->exists()) {
                    $employeeData = $document->data();
                    $employeeUid = $document->id();
                    
                    // Get FCM tokens for this employee
                    $fcmTokens = $this->getEmployeeFcmTokens($employeeUid);
                    
                    foreach ($fcmTokens as $token) {
                        $allTokens[] = [
                            'employee_uid' => $employeeUid,
                            'employee_name' => $employeeData['name'] ?? 'Unknown',
                            'employee_email' => $employeeData['email'] ?? '',
                            'token_id' => $token['id'],
                            'fcm_token' => $token['token'],
                            'device_id' => $token['device_id'],
                            'platform' => $token['platform'],
                            'created_at' => $token['created_at'],
                            'last_used' => $token['last_used'],
                        ];
                    }
                }
            }

            return $allTokens;
        } catch (\Exception $e) {
            Log::error("Failed to get all active FCM tokens: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean up old/invalid FCM tokens (older than 30 days)
     */
    public function cleanupOldFcmTokens($daysOld = 30)
    {
        try {
            $cutoffDate = now()->subDays($daysOld);
            $collection = $this->db->collection('employees');
            $documents = $collection->documents();

            $cleanedCount = 0;
            foreach ($documents as $document) {
                if ($document->exists()) {
                    $employeeUid = $document->id();
                    $fcmTokens = $this->getEmployeeFcmTokens($employeeUid);
                    
                    foreach ($fcmTokens as $token) {
                        $lastUsed = $token['last_used'] ? \Carbon\Carbon::parse($token['last_used']) : null;
                        
                        if ($lastUsed && $lastUsed->lt($cutoffDate)) {
                            $this->removeEmployeeFcmToken($employeeUid, $token['id']);
                            $cleanedCount++;
                        }
                    }
                }
            }

            Log::info("Cleaned up {$cleanedCount} old FCM tokens");
            return $cleanedCount;
        } catch (\Exception $e) {
            Log::error("Failed to cleanup old FCM tokens: " . $e->getMessage());
            return 0;
        }
    }
}