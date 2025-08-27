<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Employee;

class FirestoreRestService
{
    protected $projectId;
    protected $accessToken;
    protected $baseUrl;

    public function __construct()
    {
        $this->projectId = config('firebase.project_id');
        $this->baseUrl = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents";
        $this->accessToken = $this->getAccessToken();
    }

    protected function getAccessToken()
    {
        // Return cached token if available
        if (Cache::has('firebase_access_token')) {
            return Cache::get('firebase_access_token');
        }

        $credentialsPath = base_path('storage/app/firebase/firebase_credentials.json');
        
        if (!file_exists($credentialsPath)) {
            throw new \Exception('Firebase credentials file not found');
        }

        try {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            
            // Create JWT token
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT'
            ];
            
            $payload = [
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/datastore',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => time() + 3600,
                'iat' => time()
            ];

            $jwt = $this->createJWT($header, $payload, $credentials['private_key']);
            
            // Exchange JWT for access token
            $response = Http::post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $accessToken = $tokenData['access_token'];
                
                // Cache token for 50 minutes (tokens expire in 1 hour)
                Cache::put('firebase_access_token', $accessToken, now()->addMinutes(50));
                
                return $accessToken;
            } else {
                throw new \Exception('Failed to get access token: ' . $response->body());
            }
        } catch (\Exception $e) {
            throw new \Exception('Failed to get access token: ' . $e->getMessage());
        }
    }

    protected function createJWT($header, $payload, $privateKey)
    {
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = '';
        openssl_sign(
            $headerEncoded . '.' . $payloadEncoded,
            $signature,
            $privateKey,
            'SHA256'
        );
        
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    protected function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function getCollection($collectionName)
    {
        $url = "{$this->baseUrl}/{$collectionName}";
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ])->get($url);

        if ($response->successful()) {
            return $response->json();
        } else {
            throw new \Exception('Failed to get collection: ' . $response->body());
        }
    }

    public function createDocument($collectionName, $data, $documentId = null)
    {
        $url = $documentId 
            ? "{$this->baseUrl}/{$collectionName}/{$documentId}"
            : "{$this->baseUrl}/{$collectionName}";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'fields' => $this->convertToFirestoreFields($data)
        ]);

        if ($response->successful()) {
            return $response->json();
        } else {
            throw new \Exception('Failed to create document: ' . $response->body());
        }
    }

    public function updateDocument($collectionName, $documentId, $data)
    {
        $url = "{$this->baseUrl}/{$collectionName}/{$documentId}";
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ])->patch($url, [
            'fields' => $this->convertToFirestoreFields($data)
        ]);

        if ($response->successful()) {
            return $response->json();
        } else {
            throw new \Exception('Failed to update document: ' . $response->body());
        }
    }

    public function deleteDocument($collectionName, $documentId)
    {
        $url = "{$this->baseUrl}/{$collectionName}/{$documentId}";
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
        ])->delete($url);

        if ($response->successful()) {
            return true;
        } else {
            throw new \Exception('Failed to delete document: ' . $response->body());
        }
    }

    protected function convertToFirestoreFields($data)
    {
        $fields = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $fields[$key] = ['stringValue' => $value];
            } elseif (is_int($value)) {
                $fields[$key] = ['integerValue' => $value];
            } elseif (is_float($value)) {
                $fields[$key] = ['doubleValue' => $value];
            } elseif (is_bool($value)) {
                $fields[$key] = ['booleanValue' => $value];
            } elseif (is_null($value)) {
                $fields[$key] = ['nullValue' => null];
            } elseif (is_array($value)) {
                $fields[$key] = ['arrayValue' => ['values' => $this->convertToFirestoreFields($value)]];
            } else {
                $fields[$key] = ['stringValue' => (string) $value];
            }
        }
        
        return $fields;
    }

    public function getUsers($useCache = true)
    {
        $cacheKey = 'firestore_employees_list_rest';
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $result = $this->getCollection('employees');
            $users = [];
            
            if (isset($result['documents'])) {
                foreach ($result['documents'] as $document) {
                    $data = $this->convertFromFirestoreFields($document['fields']);
                    $data['id'] = basename($document['name']);
                    $users[] = $data;
                }
            }
            
            // Cache hasil
            if ($useCache) {
                Cache::put($cacheKey, $users, now()->addSeconds(30));
            }
            
            return $users;
        } catch (\Exception $e) {
            Log::error('Failed to get users from Firestore REST: ' . $e->getMessage());
            return [];
        }
    }

    public function getUsersCount()
    {
        try {
            $users = $this->getUsers(false);
            return count($users);
        } catch (\Exception $e) {
            Log::error('Failed to get users count from Firestore REST: ' . $e->getMessage());
            return 0;
        }
    }

    public function clearUsersCache()
    {
        Cache::forget('firestore_employees_list_rest');
        Cache::forget('firebase_access_token');
        return true;
    }

    public function syncAllEmployees()
    {
        try {
            $users = $this->getUsers(false);
            $synced = 0;
            $updated = 0;
            $errors = [];

            foreach ($users as $user) {
                try {
                    $employee = Employee::updateOrCreate(
                        ['uid' => $user['uid'] ?? ''],
                        [
                            'name' => $user['name'] ?? '',
                            'email' => $user['email'] ?? '',
                            'username' => $user['username'] ?? '',
                            'address' => $user['address'] ?? '',
                            'position' => $user['position'] ?? '',
                            'status' => $user['status'] ?? '',
                            'provider' => $user['provider'] ?? '',
                            'firestore_id' => $user['id'] ?? '',
                        ]
                    );

                    if ($employee->wasRecentlyCreated) {
                        $synced++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'user' => $user['email'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return [
                'synced' => $synced,
                'updated' => $updated,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            Log::error('Failed to sync all employees: ' . $e->getMessage());
            return [
                'synced' => 0,
                'updated' => 0,
                'errors' => [['user' => 'all', 'error' => $e->getMessage()]]
            ];
        }
    }

    public function syncEmployeeByUid($uid)
    {
        try {
            $users = $this->getUsers(false);
            
            foreach ($users as $user) {
                if (($user['uid'] ?? '') === $uid) {
                    $employee = Employee::updateOrCreate(
                        ['uid' => $user['uid'] ?? ''],
                        [
                            'name' => $user['name'] ?? '',
                            'email' => $user['email'] ?? '',
                            'username' => $user['username'] ?? '',
                            'address' => $user['address'] ?? '',
                            'position' => $user['position'] ?? '',
                            'status' => $user['status'] ?? '',
                            'provider' => $user['provider'] ?? '',
                            'firestore_id' => $user['id'] ?? '',
                        ]
                    );
                    return $employee;
                }
            }
            
            throw new \Exception("Employee with UID {$uid} not found in Firestore");
        } catch (\Exception $e) {
            Log::error('Failed to sync employee by UID: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getAbsensi()
    {
        try {
            $result = $this->getCollection('presences');
            $absensi = [];
            
            if (isset($result['documents'])) {
                foreach ($result['documents'] as $document) {
                    $data = $this->convertFromFirestoreFields($document['fields']);
                    $data['firestore_id'] = basename($document['name']);
                    $absensi[] = $data;
                }
            }
            
            return $absensi;
        } catch (\Exception $e) {
            Log::error('Failed to get absensi from Firestore REST: ' . $e->getMessage());
            return [];
        }
    }

    public function getPerizinan($limit = 100, $lastDocument = null)
    {
        try {
            $result = $this->getCollection('permits');
            $perizinan = [];
            
            if (isset($result['documents'])) {
                foreach ($result['documents'] as $document) {
                    $data = $this->convertFromFirestoreFields($document['fields']);
                    $data['firestore_id'] = basename($document['name']);
                    $perizinan[] = $data;
                }
            }
            
            return [
                'data' => $perizinan,
                'lastDocument' => null // REST API tidak mendukung pagination seperti gRPC
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get perizinan from Firestore REST: ' . $e->getMessage());
            return [
                'data' => [],
                'lastDocument' => null
            ];
        }
    }

    protected function convertFromFirestoreFields($fields)
    {
        $data = [];
        
        foreach ($fields as $key => $field) {
            if (isset($field['stringValue'])) {
                $data[$key] = $field['stringValue'];
            } elseif (isset($field['integerValue'])) {
                $data[$key] = $field['integerValue'];
            } elseif (isset($field['doubleValue'])) {
                $data[$key] = $field['doubleValue'];
            } elseif (isset($field['booleanValue'])) {
                $data[$key] = $field['booleanValue'];
            } elseif (isset($field['nullValue'])) {
                $data[$key] = null;
            } elseif (isset($field['arrayValue'])) {
                $data[$key] = $this->convertFromFirestoreFields($field['arrayValue']['values'] ?? []);
            } else {
                $data[$key] = null;
            }
        }
        
        return $data;
    }
}
