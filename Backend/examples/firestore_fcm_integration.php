<?php

/**
 * Contoh Lengkap Integrasi FCM dengan Firestore
 * 
 * File ini menunjukkan cara menggunakan sistem notifikasi
 * dengan FCM tokens yang disimpan di Firestore
 */

use App\Services\NotificationService;
use App\Services\FirestoreService;
use App\Models\Employee;
use App\Models\Notification;

// Inisialisasi services
$notificationService = new NotificationService();
$firestoreService = new FirestoreService();

// ============================================================================
// CONTOH 1: Tambah FCM Token ke Firestore
// ============================================================================

echo "🔑 Contoh 1: Tambah FCM Token ke Firestore\n";
echo "===========================================\n";

$employeeUid = "a3729a4a-7797-4139-a597-bfd434892be5"; // Ganti dengan UID employee yang sesuai
$fcmToken = "d3U-cB9WRD6trB7Ifzy_..."; // FCM token dari mobile app
$deviceId = "device_123"; // ID device (opsional)
$platform = "android"; // Platform: android, ios, web, unknown

try {
    $tokenId = $notificationService->addFcmTokenToFirestore($employeeUid, $fcmToken, $deviceId, $platform);
    echo "✅ FCM token berhasil ditambahkan ke Firestore dengan ID: {$tokenId}\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// CONTOH 2: Dapatkan FCM Tokens dari Firestore
// ============================================================================

echo "📱 Contoh 2: Dapatkan FCM Tokens dari Firestore\n";
echo "===============================================\n";

try {
    $fcmTokens = $notificationService->getEmployeeFcmTokensFromFirestore($employeeUid);
    
    if (empty($fcmTokens)) {
        echo "⚠️ Tidak ada FCM tokens ditemukan untuk employee UID: {$employeeUid}\n";
    } else {
        echo "✅ Ditemukan " . count($fcmTokens) . " FCM tokens:\n";
        
        foreach ($fcmTokens as $index => $token) {
            echo "   Token " . ($index + 1) . ":\n";
            echo "     - ID: " . $token['id'] . "\n";
            echo "     - Token: " . substr($token['token'], 0, 20) . "...\n";
            echo "     - Device: " . ($token['device_id'] ?? 'Unknown') . "\n";
            echo "     - Platform: " . $token['platform'] . "\n";
            echo "     - Created: " . $token['created_at'] . "\n";
            echo "     - Last Used: " . $token['last_used'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// CONTOH 3: Kirim Notifikasi ke Employee dengan FCM Tokens dari Firestore
// ============================================================================

echo "📱 Contoh 3: Kirim Notifikasi dengan FCM Tokens dari Firestore\n";
echo "==============================================================\n";

try {
    $result = $notificationService->sendToEmployeeWithFirestoreTokens(
        $employeeUid,
        'Test Notifikasi dari Backend',
        'Ini adalah test notifikasi yang menggunakan FCM tokens dari Firestore.',
        [
            'action' => 'test_notification',
            'timestamp' => now()->toISOString(),
            'source' => 'backend_integration',
            'employee_uid' => $employeeUid,
        ],
        [
            'type' => Notification::TYPE_GENERAL,
            'priority' => Notification::PRIORITY_NORMAL,
        ]
    );

    if ($result) {
        echo "✅ Notifikasi berhasil dikirim menggunakan tokens dari Firestore!\n";
        echo "📱 Cek di mobile app apakah notifikasi diterima\n";
    } else {
        echo "❌ Gagal mengirim notifikasi\n";
        echo "💡 Kemungkinan penyebab:\n";
        echo "   - FCM token tidak valid atau expired\n";
        echo "   - Firebase credentials tidak benar\n";
        echo "   - Network connectivity issues\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// CONTOH 4: Kirim Notifikasi Massal ke Semua Employee
// ============================================================================

echo "📢 Contoh 4: Kirim Notifikasi Massal ke Semua Employee\n";
echo "=====================================================\n";

try {
    $results = $notificationService->sendToAllEmployeesWithFirestoreTokens(
        'Pengumuman Penting',
        'Ini adalah pengumuman penting untuk semua karyawan menggunakan FCM tokens dari Firestore.',
        [
            'action' => 'announcement',
            'timestamp' => now()->toISOString(),
            'source' => 'mass_notification',
        ],
        [
            'type' => Notification::TYPE_ANNOUNCEMENT,
            'priority' => Notification::PRIORITY_HIGH,
        ]
    );

    $successCount = count(array_filter($results, function($result) {
        return $result['success'];
    }));

    echo "✅ Mass notification sent: {$successCount} dari " . count($results) . " berhasil\n";
    
    if ($successCount > 0) {
        echo "📱 Cek di mobile apps apakah notifikasi diterima\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// CONTOH 5: Hapus FCM Token dari Firestore
// ============================================================================

echo "🗑️ Contoh 5: Hapus FCM Token dari Firestore\n";
echo "===========================================\n";

// Get tokens first
$fcmTokens = $notificationService->getEmployeeFcmTokensFromFirestore($employeeUid);

if (!empty($fcmTokens)) {
    $tokenToRemove = $fcmTokens[0]['id']; // Remove first token as example
    
    try {
        $result = $notificationService->removeFcmTokenFromFirestore($employeeUid, $tokenToRemove);
        
        if ($result) {
            echo "✅ FCM token berhasil dihapus dari Firestore: {$tokenToRemove}\n";
        } else {
            echo "❌ Gagal menghapus FCM token\n";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️ Tidak ada FCM tokens untuk dihapus\n";
}

echo "\n";

// ============================================================================
// CONTOH 6: Cleanup Old FCM Tokens
// ============================================================================

echo "🧹 Contoh 6: Cleanup Old FCM Tokens\n";
echo "===================================\n";

try {
    $cleanedCount = $notificationService->cleanupOldFcmTokensFromFirestore(30); // 30 days old
    echo "✅ Cleaned up {$cleanedCount} old FCM tokens dari Firestore\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// CONTOH 7: Integrasi dengan Fitur Existing
// ============================================================================

echo "🔗 Contoh 7: Integrasi dengan Fitur Existing\n";
echo "============================================\n";

// Simulasi check-in dengan notifikasi
echo "Simulasi check-in dengan notifikasi:\n";

try {
    // Simulasi proses check-in
    $presenceId = 123; // ID presence yang dibuat
    
    // Kirim notifikasi check-in berhasil
    $result = $notificationService->sendToEmployeeWithFirestoreTokens(
        $employeeUid,
        'Check-in Berhasil',
        'Anda telah berhasil check-in pada ' . now()->format('H:i'),
        [
            'action' => 'view_presence',
            'presence_id' => $presenceId,
            'date' => now()->format('Y-m-d'),
            'check_in_time' => now()->format('H:i:s'),
        ],
        [
            'type' => Notification::TYPE_PRESENCE,
            'priority' => Notification::PRIORITY_NORMAL,
        ]
    );

    if ($result) {
        echo "✅ Notifikasi check-in berhasil dikirim\n";
    } else {
        echo "❌ Gagal mengirim notifikasi check-in\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// CONTOH 8: Statistik Notifikasi
// ============================================================================

echo "📊 Contoh 8: Statistik Notifikasi\n";
echo "=================================\n";

try {
    $stats = $notificationService->getStatistics();
    
    echo "📈 Notification Statistics:\n";
    echo "- Total: " . $stats['total'] . "\n";
    echo "- Sent: " . $stats['sent'] . "\n";
    echo "- Pending: " . $stats['pending'] . "\n";
    echo "- Failed: " . $stats['failed'] . "\n";
    echo "- Scheduled: " . $stats['scheduled'] . "\n";
    echo "- Unread: " . $stats['unread'] . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// CONTOH 9: API Endpoints untuk Mobile App
// ============================================================================

echo "🌐 Contoh 9: API Endpoints untuk Mobile App\n";
echo "===========================================\n";

echo "Mobile app dapat menggunakan endpoint berikut:\n\n";

echo "1. Tambah FCM Token:\n";
echo "   POST /api/notifications/fcm-token/firestore\n";
echo "   Body: {\n";
echo "     \"fcm_token\": \"your_fcm_token\",\n";
echo "     \"device_id\": \"device_123\",\n";
echo "     \"platform\": \"android\"\n";
echo "   }\n\n";

echo "2. Dapatkan FCM Tokens:\n";
echo "   GET /api/notifications/fcm-tokens/firestore\n\n";

echo "3. Hapus FCM Token:\n";
echo "   DELETE /api/notifications/fcm-token/firestore\n";
echo "   Body: {\n";
echo "     \"token_id\": \"token_id_from_firestore\"\n";
echo "   }\n\n";

echo "4. Test Notification:\n";
echo "   POST /api/notifications/test-firestore\n";
echo "   Body: {\n";
echo "     \"title\": \"Test Title\",\n";
echo "     \"body\": \"Test Body\",\n";
echo "     \"employee_uid\": \"employee_uid\"\n";
echo "   }\n\n";

echo "5. Get Notifications:\n";
echo "   GET /api/notifications\n\n";

echo "6. Mark as Read:\n";
echo "   PATCH /api/notifications/{id}/read\n\n";

echo "=== SELESAI ===\n";
echo "Sistem notifikasi FCM dengan Firestore sudah siap digunakan!\n";
echo "Pastikan FCM tokens di Firestore valid dan mobile app sudah terintegrasi.\n";
