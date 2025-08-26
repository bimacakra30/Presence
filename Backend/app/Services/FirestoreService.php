<?php

namespace App\Services;

use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FirestoreService
{
    protected $db;
    protected $cacheTimeout = 30; // Cache selama 30 detik untuk realtime experience

    public function __construct()
    {
        $this->db = new FirestoreClient([
            'keyFilePath' => base_path('storage/app/firebase/firebase_credentials.json'),
            'transport' => 'rest',
        ]);
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
     * Get specific user by ID
     */
    public function getUser($id)
    {
        $cacheKey = "firestore_employee_{$id}";
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $collection = $this->db->collection('employees');
        $document = $collection->document((string)$id)->snapshot();

        if ($document->exists()) {
            $data = $document->data();
            $data['id'] = $document->id();
            $data = $this->normalizeUserData($data);
            
            // Cache individual user and track the key
            Cache::put($cacheKey, $data, now()->addSeconds($this->cacheTimeout));
            $this->addToTrackedKeys($cacheKey);
            
            return $data;
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
        $collection = $this->db->collection('presence');
        $collection->document($documentId)->delete();
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
            $collection = $this->db->collection('employees');
            $document = $collection->document($employeeUid);
            
            if (!$document->snapshot()->exists()) {
                return [];
            }

            // Get fcmTokens subcollection
            $fcmTokensCollection = $document->collection('fcmTokens');
            $tokens = $fcmTokensCollection->documents();

            $fcmTokens = [];
            foreach ($tokens as $token) {
                if ($token->exists()) {
                    $tokenData = $token->data();
                    $fcmTokens[] = [
                        'id' => $token->id(),
                        'token' => $tokenData['token'] ?? '',
                        'device_id' => $tokenData['deviceId'] ?? '',
                        'platform' => $tokenData['platform'] ?? 'unknown',
                        'created_at' => $tokenData['createdAt'] ?? null,
                        'last_used' => $tokenData['lastUsed'] ?? null,
                    ];
                }
            }

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
            
            if (!$document->snapshot()->exists()) {
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
            
            if (!$document->snapshot()->exists()) {
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
            
            if (!$document->snapshot()->exists()) {
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