<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;

class NotificationService
{
    protected $fcmEndpoint = 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send';
    protected $projectId;
    protected $accessToken;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id');
        // Don't get access token in constructor to avoid JWT issues
        // $this->accessToken = $this->getAccessToken();
    }

    protected function getAccessToken()
    {
        // Return cached token if available and not expired
        if ($this->accessToken) {
            return $this->accessToken;
        }
        
        $credentialsPath = base_path('storage/app/firebase/firebase_credentials.json');
        
        if (!file_exists($credentialsPath)) {
            throw new \Exception('Firebase credentials file not found');
        }

        try {
            // Use Google Auth library for more reliable authentication
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                $credentialsPath
            );
            
            $this->accessToken = $credentials->fetchAuthToken()['access_token'];
            return $this->accessToken;
        } catch (\Exception $e) {
            throw new \Exception('Failed to get access token: ' . $e->getMessage());
        }
    }



    /**
     * Send notification to single recipient
     */
    public function sendToRecipient($recipient, $title, $body, $data = [], $options = [])
    {
        $notification = $this->createNotification($recipient, $title, $body, $data, $options);
        
        if ($notification) {
            return $this->sendNotification($notification);
        }
        
        return false;
    }

    /**
     * Send notification to multiple recipients
     */
    public function sendToMultipleRecipients($recipients, $title, $body, $data = [], $options = [])
    {
        $notifications = [];
        $results = [];

        foreach ($recipients as $recipient) {
            $notification = $this->createNotification($recipient, $title, $body, $data, $options);
            if ($notification) {
                $notifications[] = $notification;
            }
        }

        // Send notifications in batches
        $batchSize = 100; // FCM allows up to 1000 tokens per request
        $batches = array_chunk($notifications, $batchSize);

        foreach ($batches as $batch) {
            $result = $this->sendBatchNotifications($batch);
            $results = array_merge($results, $result);
        }

        return $results;
    }

    /**
     * Send notification to all employees
     */
    public function sendToAllEmployees($title, $body, $data = [], $options = [])
    {
        $employees = Employee::where('status', 'active')->get();
        return $this->sendToMultipleRecipients($employees, $title, $body, $data, $options);
    }

    /**
     * Send notification to employees by position
     */
    public function sendToEmployeesByPosition($position, $title, $body, $data = [], $options = [])
    {
        $employees = Employee::where('status', 'active')
            ->where('position', $position)
            ->get();
        
        return $this->sendToMultipleRecipients($employees, $title, $body, $data, $options);
    }

    /**
     * Create notification record
     */
    protected function createNotification($recipient, $title, $body, $data = [], $options = [])
    {
        try {
            $notification = Notification::create([
                'title' => $title,
                'body' => $body,
                'type' => $options['type'] ?? Notification::TYPE_GENERAL,
                'data' => $data,
                'recipient_type' => get_class($recipient),
                'recipient_id' => $recipient->id,
                'fcm_token' => $recipient->fcm_token ?? null,
                'status' => Notification::STATUS_PENDING,
                'priority' => $options['priority'] ?? Notification::PRIORITY_NORMAL,
                'image_url' => $options['image_url'] ?? null,
                'action_url' => $options['action_url'] ?? null,
                'scheduled_at' => $options['scheduled_at'] ?? null,
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('Failed to create notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send single notification
     */
    protected function sendNotification(Notification $notification)
    {
        if (!$notification->fcm_token) {
            $notification->markAsFailed();
            Log::warning("No FCM token for notification ID: {$notification->id}");
            return false;
        }

        try {
            $payload = $this->buildFcmPayload($notification);
            $endpoint = str_replace('{project_id}', $this->projectId, $this->fcmEndpoint);
            
            // Get fresh access token
            $accessToken = $this->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($endpoint, $payload);

            if ($response->successful()) {
                $result = $response->json();
                
                if (isset($result['name'])) {
                    $notification->markAsSent();
                    Log::info("Notification sent successfully: {$notification->id}");
                    return true;
                } else {
                    $notification->markAsFailed();
                    Log::error("FCM send failed for notification {$notification->id}: " . json_encode($result));
                    return false;
                }
            } else {
                $notification->markAsFailed();
                Log::error("FCM HTTP error for notification {$notification->id}: " . $response->status() . " - " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            $notification->markAsFailed();
            Log::error("Exception sending notification {$notification->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send batch notifications
     */
    protected function sendBatchNotifications($notifications)
    {
        // FCM V1 API doesn't support batch sending in the same way
        // We'll send them individually but in parallel
        $results = [];
        
        foreach ($notifications as $notification) {
            $result = $this->sendNotification($notification);
            $results[] = [
                'success' => $result,
                'notification' => $notification
            ];
        }
        
        return $results;
    }

    /**
     * Build FCM payload
     */
    protected function buildFcmPayload(Notification $notification)
    {
        // Convert all data values to strings (FCM requirement)
        $data = [];
        if ($notification->data) {
            foreach ($notification->data as $key => $value) {
                $data[$key] = (string) $value;
            }
        }

        $payload = [
            'message' => [
                'token' => $notification->fcm_token,
                'notification' => [
                    'title' => $notification->title,
                    'body' => $notification->body,
                ],
                'data' => $data,
                'android' => [
                    'priority' => $this->getFcmPriority($notification->priority),
                    'notification' => [
                        'sound' => 'default',
                        'channel_id' => 'default',
                    ]
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ]
                    ]
                ]
            ]
        ];

        if ($notification->image_url) {
            $payload['message']['notification']['image'] = $notification->image_url;
        }

        return $payload;
    }

    /**
     * Convert notification priority to FCM priority
     */
    protected function getFcmPriority($priority)
    {
        switch ($priority) {
            case Notification::PRIORITY_HIGH:
            case Notification::PRIORITY_URGENT:
                return 'high';
            default:
                return 'normal';
        }
    }

    /**
     * Schedule notification
     */
    public function scheduleNotification($recipient, $title, $body, $scheduledAt, $data = [], $options = [])
    {
        $options['scheduled_at'] = $scheduledAt;
        $options['status'] = Notification::STATUS_SCHEDULED;
        
        return $this->createNotification($recipient, $title, $body, $data, $options);
    }

    /**
     * Process scheduled notifications
     */
    public function processScheduledNotifications()
    {
        $scheduledNotifications = Notification::scheduled()
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($scheduledNotifications as $notification) {
            $notification->update(['status' => Notification::STATUS_PENDING]);
            $this->sendNotification($notification);
        }

        return $scheduledNotifications->count();
    }

    /**
     * Update FCM token for recipient
     */
    public function updateFcmToken($recipient, $fcmToken)
    {
        if ($recipient instanceof Employee) {
            $recipient->update(['fcm_token' => $fcmToken]);
        } elseif ($recipient instanceof \App\Models\User) {
            $recipient->update(['fcm_token' => $fcmToken]);
        }

        // Update existing pending notifications
        Notification::where('recipient_type', get_class($recipient))
            ->where('recipient_id', $recipient->id)
            ->whereNull('fcm_token')
            ->update(['fcm_token' => $fcmToken]);
    }

    /**
     * Get notification statistics
     */
    public function getStatistics()
    {
        return [
            'total' => Notification::count(),
            'sent' => Notification::where('status', Notification::STATUS_SENT)->count(),
            'pending' => Notification::where('status', Notification::STATUS_PENDING)->count(),
            'failed' => Notification::where('status', Notification::STATUS_FAILED)->count(),
            'scheduled' => Notification::where('status', Notification::STATUS_SCHEDULED)->count(),
            'unread' => Notification::whereNull('read_at')->count(),
        ];
    }

    /**
     * Send notification to employee using FCM tokens from Firestore
     */
    public function sendToEmployeeWithFirestoreTokens($employeeUid, $title, $body, $data = [], $options = [])
    {
        try {
            Log::info("NotificationService: Starting sendToEmployeeWithFirestoreTokens for employee {$employeeUid} with title '{$title}'");
            
            // Check for duplicate notification first
            if ($this->isDuplicateNotification($employeeUid, $title, $data, $options)) {
                Log::info("NotificationService: Duplicate notification prevented for employee {$employeeUid}: {$title}");
                return true; // Return true to avoid retry
            }
            
            Log::info("NotificationService: Proceeding with notification for employee {$employeeUid}: {$title}");

            $firestoreService = new \App\Services\FirestoreService();
            $fcmTokens = $firestoreService->getEmployeeFcmTokens($employeeUid);
            
            if (empty($fcmTokens)) {
                Log::warning("No FCM tokens found for employee UID: {$employeeUid}");
                
                // Fallback: Create notification in database even without FCM token
                $employee = \App\Models\Employee::where('uid', $employeeUid)->first();
                if ($employee) {
                    $notification = $this->createNotification($employee, $title, $body, $data, $options);
                    Log::info("Created fallback notification in database for employee {$employeeUid}");
                    return true;
                }
                return false;
            }

            $successCount = 0;
            $totalTokens = count($fcmTokens);
            $sentTokens = []; // Track sent tokens to prevent duplicates

            foreach ($fcmTokens as $tokenData) {
                $fcmToken = $tokenData['token'];
                
                // Skip if we've already sent to this token
                if (in_array($fcmToken, $sentTokens)) {
                    Log::info("Skipping duplicate token for employee {$employeeUid}: " . substr($fcmToken, 0, 20) . "...");
                    continue;
                }
                
                // Create notification record
                $notification = $this->createNotificationFromToken($employeeUid, $title, $body, $data, $options, $fcmToken);
                
                if ($notification) {
                    // Send notification
                    $result = $this->sendNotification($notification);
                    
                    if ($result) {
                        $successCount++;
                        $sentTokens[] = $fcmToken; // Track this token as sent
                        // Update last used timestamp
                        $firestoreService->updateFcmTokenLastUsed($employeeUid, $tokenData['id']);
                    }
                }
            }

            Log::info("Sent notification to employee {$employeeUid}: {$successCount}/{$totalTokens} tokens successful");
            return $successCount > 0;
            
        } catch (\Exception $e) {
            Log::error("Failed to send notification to employee {$employeeUid}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple employees using Firestore tokens
     */
    public function sendToMultipleEmployeesWithFirestoreTokens($employeeUids, $title, $body, $data = [], $options = [])
    {
        $results = [];
        
        foreach ($employeeUids as $employeeUid) {
            $result = $this->sendToEmployeeWithFirestoreTokens($employeeUid, $title, $body, $data, $options);
            $results[] = [
                'employee_uid' => $employeeUid,
                'success' => $result
            ];
        }
        
        return $results;
    }

    /**
     * Send notification to all employees using Firestore tokens
     */
    public function sendToAllEmployeesWithFirestoreTokens($title, $body, $data = [], $options = [])
    {
        try {
            $firestoreService = new \App\Services\FirestoreService();
            $allTokens = $firestoreService->getAllActiveFcmTokens();
            
            if (empty($allTokens)) {
                Log::warning("No active FCM tokens found in Firestore");
                return [];
            }

            $results = [];
            $batchSize = 100; // Process in batches
            $batches = array_chunk($allTokens, $batchSize);

            foreach ($batches as $batch) {
                foreach ($batch as $tokenData) {
                    $employeeUid = $tokenData['employee_uid'];
                    $fcmToken = $tokenData['fcm_token'];
                    
                    // Create notification record
                    $notification = $this->createNotificationFromToken($employeeUid, $title, $body, $data, $options, $fcmToken);
                    
                    if ($notification) {
                        // Send notification
                        $result = $this->sendNotification($notification);
                        
                        $results[] = [
                            'employee_uid' => $employeeUid,
                            'employee_name' => $tokenData['employee_name'],
                            'token_id' => $tokenData['token_id'],
                            'success' => $result
                        ];
                        
                        if ($result) {
                            // Update last used timestamp
                            $firestoreService->updateFcmTokenLastUsed($employeeUid, $tokenData['token_id']);
                        }
                    }
                }
            }

            Log::info("Sent notification to all employees: " . count($results) . " notifications processed");
            return $results;
            
        } catch (\Exception $e) {
            Log::error("Failed to send notification to all employees: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create notification record from FCM token
     */
    protected function createNotificationFromToken($employeeUid, $title, $body, $data = [], $options = [], $fcmToken = null)
    {
        try {
            // Find employee by UID
            $employee = \App\Models\Employee::where('uid', $employeeUid)->first();
            
            if (!$employee) {
                Log::warning("Employee not found in local database for UID: {$employeeUid}");
                return null;
            }

            $notification = Notification::create([
                'title' => $title,
                'body' => $body,
                'type' => $options['type'] ?? Notification::TYPE_GENERAL,
                'data' => $data,
                'recipient_type' => Employee::class,
                'recipient_id' => $employee->id,
                'fcm_token' => $fcmToken,
                'status' => Notification::STATUS_PENDING,
                'priority' => $options['priority'] ?? Notification::PRIORITY_NORMAL,
                'image_url' => $options['image_url'] ?? null,
                'action_url' => $options['action_url'] ?? null,
                'scheduled_at' => $options['scheduled_at'] ?? null,
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('Failed to create notification from token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Add FCM token to Firestore for employee
     */
    public function addFcmTokenToFirestore($employeeUid, $fcmToken, $deviceId = null, $platform = 'unknown')
    {
        try {
            $firestoreService = new \App\Services\FirestoreService();
            $tokenId = $firestoreService->addEmployeeFcmToken($employeeUid, $fcmToken, $deviceId, $platform);
            
            Log::info("FCM token added to Firestore for employee {$employeeUid}: {$tokenId}");
            return $tokenId;
            
        } catch (\Exception $e) {
            Log::error("Failed to add FCM token to Firestore: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Remove FCM token from Firestore for employee
     */
    public function removeFcmTokenFromFirestore($employeeUid, $tokenId)
    {
        try {
            $firestoreService = new \App\Services\FirestoreService();
            $result = $firestoreService->removeEmployeeFcmToken($employeeUid, $tokenId);
            
            Log::info("FCM token removed from Firestore for employee {$employeeUid}: {$tokenId}");
            return $result;
            
        } catch (\Exception $e) {
            Log::error("Failed to remove FCM token from Firestore: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get FCM tokens for employee from Firestore
     */
    public function getEmployeeFcmTokensFromFirestore($employeeUid)
    {
        try {
            $firestoreService = new \App\Services\FirestoreService();
            return $firestoreService->getEmployeeFcmTokens($employeeUid);
            
        } catch (\Exception $e) {
            Log::error("Failed to get FCM tokens from Firestore: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean up old FCM tokens from Firestore
     */
    public function cleanupOldFcmTokensFromFirestore($daysOld = 30)
    {
        try {
            $firestoreService = new \App\Services\FirestoreService();
            $cleanedCount = $firestoreService->cleanupOldFcmTokens($daysOld);
            
            Log::info("Cleaned up {$cleanedCount} old FCM tokens from Firestore");
            return $cleanedCount;
            
        } catch (\Exception $e) {
            Log::error("Failed to cleanup old FCM tokens from Firestore: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if notification is duplicate based on recent notifications
     */
    protected function isDuplicateNotification($employeeUid, $title, $data = [], $options = [])
    {
        try {
            // Create unique notification fingerprint
            $notificationFingerprint = $this->createNotificationFingerprint($employeeUid, $title, $data, $options);
            
            // Check cache first (for immediate duplicate prevention)
            $cacheKey = "notification_fingerprint:{$notificationFingerprint}";
            if (Cache::has($cacheKey)) {
                Log::info("Duplicate notification prevented via cache for employee {$employeeUid}: {$title}");
                return true;
            }

            // Find employee by UID
            $employee = \App\Models\Employee::where('uid', $employeeUid)->first();
            if (!$employee) {
                return false; // If employee not found, allow notification
            }

            // Check for duplicate in the last 15 minutes (increased for better coverage)
            $fifteenMinutesAgo = now()->subMinutes(15);
            
            $query = Notification::where('recipient_type', Employee::class)
                               ->where('recipient_id', $employee->id)
                               ->where('title', $title)
                               ->where('created_at', '>=', $fifteenMinutesAgo);

            // Add additional checks based on notification type and data
            if (isset($options['type'])) {
                $query->where('type', $options['type']);
            }

            // Check for specific data fields that indicate uniqueness
            if (isset($data['permit_id'])) {
                $query->whereJsonContains('data->permit_id', $data['permit_id']);
            }

            if (isset($data['presence_id'])) {
                $query->whereJsonContains('data->presence_id', $data['presence_id']);
            }

            if (isset($data['action'])) {
                $query->whereJsonContains('data->action', $data['action']);
            }

            // Special handling for status change notifications
            if (strpos($title, 'Status') !== false && isset($data['status'])) {
                $query->whereJsonContains('data->status', $data['status']);
                
                // Also check for the same permit data (tanggal_mulai, tanggal_selesai, jenis_perizinan)
                if (isset($data['tanggal_mulai'])) {
                    $query->whereJsonContains('data->tanggal_mulai', $data['tanggal_mulai']);
                }
                if (isset($data['tanggal_selesai'])) {
                    $query->whereJsonContains('data->tanggal_selesai', $data['tanggal_selesai']);
                }
                if (isset($data['jenis_perizinan'])) {
                    $query->whereJsonContains('data->jenis_perizinan', $data['jenis_perizinan']);
                }
            }

            // Check if similar notification exists
            $duplicateCount = $query->count();
            
            if ($duplicateCount > 0) {
                Log::info("Duplicate notification detected for employee {$employeeUid}: {$title} (count: {$duplicateCount})");
                return true;
            }

            // Set cache to prevent immediate duplicates (10 minutes cache for status notifications)
            $cacheDuration = strpos($title, 'Status') !== false ? 10 : 5;
            Cache::put($cacheKey, true, now()->addMinutes($cacheDuration));
            
            return false;
            
        } catch (\Exception $e) {
            Log::error("Error checking for duplicate notification: " . $e->getMessage());
            return false; // If error occurs, allow notification to proceed
        }
    }

    /**
     * Create unique notification fingerprint for duplicate detection
     */
    protected function createNotificationFingerprint($employeeUid, $title, $data = [], $options = [])
    {
        $fingerprintData = [
            'employee_uid' => $employeeUid,
            'title' => $title,
            'type' => $options['type'] ?? 'general',
            'action' => $data['action'] ?? null,
        ];
        
        // For permit notifications, include relevant data but exclude IDs that change
        if (isset($options['type']) && $options['type'] === 'permit') {
            $fingerprintData['jenis_perizinan'] = $data['jenis_perizinan'] ?? null;
            $fingerprintData['tanggal_mulai'] = $data['tanggal_mulai'] ?? null;
            $fingerprintData['tanggal_selesai'] = $data['tanggal_selesai'] ?? null;
            
            // For status change notifications, include status but exclude permit_id
            if (strpos($title, 'Status') !== false && isset($data['status'])) {
                $fingerprintData['status'] = $data['status'];
                // Add a unique identifier for status change notifications
                $fingerprintData['status_change'] = true;
            }
        }
        
        // For presence notifications, include relevant data but exclude IDs that change
        if (isset($options['type']) && $options['type'] === 'presence') {
            $fingerprintData['date'] = $data['date'] ?? null;
        }
        
        // Remove null values to create consistent fingerprint
        $fingerprintData = array_filter($fingerprintData, function($value) {
            return $value !== null;
        });
        
        return md5(json_encode($fingerprintData));
    }

    /**
     * Create unique notification ID for tracking
     */
    protected function createNotificationId($employeeUid, $title, $data = [])
    {
        $uniqueData = [
            'employee_uid' => $employeeUid,
            'title' => $title,
            'permit_id' => $data['permit_id'] ?? null,
            'presence_id' => $data['presence_id'] ?? null,
            'action' => $data['action'] ?? null,
            'timestamp' => now()->timestamp
        ];
        
        return md5(json_encode($uniqueData));
    }
}
