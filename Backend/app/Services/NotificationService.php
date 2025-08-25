<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    protected $fcmEndpoint = 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send';
    protected $projectId;
    protected $accessToken;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id');
        $this->accessToken = $this->getAccessToken();
    }

    protected function getAccessToken()
    {
        $credentialsPath = base_path('storage/app/firebase/firebase_credentials.json');
        
        if (!file_exists($credentialsPath)) {
            throw new \Exception('Firebase credentials file not found');
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);
        
        // Create JWT token
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];
        
        $payload = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => time() + 3600,
            'iat' => time()
        ];
        
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = '';
        openssl_sign(
            $headerEncoded . '.' . $payloadEncoded,
            $signature,
            $credentials['private_key'],
            'SHA256'
        );
        
        $signatureEncoded = $this->base64UrlEncode($signature);
        $jwt = $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
        
        // Exchange JWT for access token
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]);
        
        if ($response->successful()) {
            return $response->json('access_token');
        }
        
        throw new \Exception('Failed to get access token: ' . $response->body());
    }

    protected function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
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
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
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
}
