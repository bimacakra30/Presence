<?php

/**
 * Contoh Testing API FCM Token
 * 
 * Script ini menunjukkan cara test API untuk update FCM token
 * dan mengirim notifikasi dari backend
 */

// ============================================================================
// CONTOH 1: Update FCM Token via API
// ============================================================================

echo "🔑 Testing Update FCM Token via API\n";
echo "=====================================\n";

// Ganti dengan URL API Anda
$baseUrl = 'http://localhost:8000/api';
$authToken = 'your_auth_token_here'; // Ganti dengan token auth yang valid
$employeeId = 51; // Ganti dengan ID employee yang valid

// FCM token yang valid (contoh - ganti dengan token yang sebenarnya)
$fcmToken = 'fMEP0vJqS6:APA91bHqX...'; // Token FCM yang valid dari Firebase

$url = $baseUrl . '/notifications/fcm-token';
$data = [
    'fcm_token' => $fcmToken
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $authToken,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: {$httpCode}\n";
echo "Response: {$response}\n\n";

// ============================================================================
// CONTOH 2: Test Kirim Notifikasi via Backend Service
// ============================================================================

echo "📱 Testing Send Notification via Backend Service\n";
echo "================================================\n";

// Include Laravel autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\NotificationService;
use App\Models\Employee;
use App\Models\Notification;

$notificationService = new NotificationService();
$employee = Employee::find($employeeId);

if ($employee) {
    echo "✅ Employee found: {$employee->name}\n";
    
    // Update FCM token langsung di database
    $employee->update(['fcm_token' => $fcmToken]);
    echo "✅ FCM token updated in database\n";
    
    // Test kirim notifikasi
    $result = $notificationService->sendToRecipient(
        $employee,
        'Test Notifikasi dari Backend',
        'Ini adalah test notifikasi yang dikirim dari backend website.',
        [
            'action' => 'test_notification',
            'timestamp' => now()->toISOString(),
            'source' => 'backend_website',
        ],
        [
            'type' => Notification::TYPE_GENERAL,
            'priority' => Notification::PRIORITY_NORMAL,
        ]
    );
    
    if ($result) {
        echo "✅ Notifikasi berhasil dikirim!\n";
    } else {
        echo "❌ Gagal mengirim notifikasi\n";
    }
} else {
    echo "❌ Employee tidak ditemukan\n";
}

echo "\n=== SELESAI ===\n";
