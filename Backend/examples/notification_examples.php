<?php

/**
 * Contoh Penggunaan Sistem Notifikasi Mobile
 * 
 * File ini berisi contoh-contoh cara menggunakan NotificationService
 * untuk berbagai skenario notifikasi.
 */

use App\Services\NotificationService;
use App\Models\Employee;
use App\Models\Notification;

// Inisialisasi service
$notificationService = new NotificationService();

// ============================================================================
// CONTOH 1: Kirim Notifikasi ke Satu Karyawan
// ============================================================================

$employee = Employee::find(1);

$result = $notificationService->sendToRecipient(
    $employee,
    'Selamat Pagi!',
    'Selamat datang di kantor hari ini. Jangan lupa untuk check-in.',
    [
        'action' => 'check_in',
        'date' => now()->format('Y-m-d'),
    ],
    [
        'type' => Notification::TYPE_PRESENCE,
        'priority' => Notification::PRIORITY_NORMAL,
        'image_url' => 'https://example.com/images/welcome.jpg',
        'action_url' => 'https://app.example.com/check-in',
    ]
);

echo "Notifikasi terkirim: " . ($result ? 'Berhasil' : 'Gagal') . "\n";

// ============================================================================
// CONTOH 2: Kirim Notifikasi ke Semua Karyawan
// ============================================================================

$result = $notificationService->sendToAllEmployees(
    'Pengumuman Penting',
    'Besok kantor akan tutup untuk libur nasional. Selamat berlibur!',
    [
        'action' => 'announcement',
        'date' => now()->format('Y-m-d'),
        'holiday' => true,
    ],
    [
        'type' => Notification::TYPE_ANNOUNCEMENT,
        'priority' => Notification::PRIORITY_HIGH,
        'image_url' => 'https://example.com/images/holiday.jpg',
    ]
);

echo "Notifikasi ke semua karyawan: " . count($result) . " berhasil dikirim\n";

// ============================================================================
// CONTOH 3: Kirim Notifikasi ke Karyawan Berdasarkan Posisi
// ============================================================================

$result = $notificationService->sendToEmployeesByPosition(
    'Manager',
    'Meeting Manager',
    'Ada meeting penting untuk semua manager hari ini pukul 14:00.',
    [
        'action' => 'meeting',
        'time' => '14:00',
        'location' => 'Ruang Meeting Utama',
    ],
    [
        'type' => Notification::TYPE_GENERAL,
        'priority' => Notification::PRIORITY_HIGH,
    ]
);

echo "Notifikasi ke manager: " . count($result) . " berhasil dikirim\n";

// ============================================================================
// CONTOH 4: Notifikasi Terjadwal
// ============================================================================

$scheduledTime = now()->addHours(2); // 2 jam dari sekarang

$notification = $notificationService->scheduleNotification(
    $employee,
    'Reminder Check-out',
    'Jangan lupa untuk check-out sebelum pulang.',
    $scheduledTime,
    [
        'action' => 'check_out_reminder',
        'time' => '17:00',
    ],
    [
        'type' => Notification::TYPE_PRESENCE,
        'priority' => Notification::PRIORITY_NORMAL,
    ]
);

echo "Notifikasi terjadwal dibuat dengan ID: " . $notification->id . "\n";

// ============================================================================
// CONTOH 5: Update FCM Token
// ============================================================================

$fcmToken = 'sample_fcm_token_from_mobile_app';
$notificationService->updateFcmToken($employee, $fcmToken);

echo "FCM token berhasil diupdate\n";

// ============================================================================
// CONTOH 6: Dapatkan Statistik Notifikasi
// ============================================================================

$stats = $notificationService->getStatistics();

echo "Statistik Notifikasi:\n";
echo "- Total: " . $stats['total'] . "\n";
echo "- Terkirim: " . $stats['sent'] . "\n";
echo "- Pending: " . $stats['pending'] . "\n";
echo "- Gagal: " . $stats['failed'] . "\n";
echo "- Terjadwal: " . $stats['scheduled'] . "\n";
echo "- Belum dibaca: " . $stats['unread'] . "\n";

// ============================================================================
// CONTOH 7: Proses Notifikasi Terjadwal
// ============================================================================

$processedCount = $notificationService->processScheduledNotifications();
echo "Notifikasi terjadwal yang diproses: " . $processedCount . "\n";

// ============================================================================
// CONTOH 8: Query Notifikasi dari Database
// ============================================================================

// Dapatkan notifikasi yang belum dibaca
$unreadNotifications = Notification::unread()
    ->where('recipient_type', Employee::class)
    ->where('recipient_id', $employee->id)
    ->get();

echo "Notifikasi belum dibaca: " . $unreadNotifications->count() . "\n";

// Dapatkan notifikasi berdasarkan tipe
$presenceNotifications = Notification::byType(Notification::TYPE_PRESENCE)
    ->where('recipient_type', Employee::class)
    ->where('recipient_id', $employee->id)
    ->get();

echo "Notifikasi presence: " . $presenceNotifications->count() . "\n";

// Dapatkan notifikasi dengan prioritas tinggi
$highPriorityNotifications = Notification::byPriority(Notification::PRIORITY_HIGH)
    ->where('recipient_type', Employee::class)
    ->where('recipient_id', $employee->id)
    ->get();

echo "Notifikasi prioritas tinggi: " . $highPriorityNotifications->count() . "\n";

// ============================================================================
// CONTOH 9: Mark Notifikasi sebagai Dibaca
// ============================================================================

foreach ($unreadNotifications as $notification) {
    $notification->markAsRead();
    echo "Notifikasi ID {$notification->id} ditandai sebagai dibaca\n";
}

// ============================================================================
// CONTOH 10: Batch Operations
// ============================================================================

// Kirim notifikasi ke multiple karyawan
$employees = Employee::where('status', 'active')->limit(5)->get();

$results = $notificationService->sendToMultipleRecipients(
    $employees,
    'Test Batch Notification',
    'Ini adalah test notifikasi batch.',
    [
        'action' => 'test_batch',
        'timestamp' => now()->toISOString(),
    ],
    [
        'type' => Notification::TYPE_GENERAL,
        'priority' => Notification::PRIORITY_LOW,
    ]
);

$successCount = count(array_filter($results, function($result) {
    return $result['success'];
}));

echo "Batch notification: {$successCount} dari " . count($results) . " berhasil\n";

// ============================================================================
// CONTOH 11: Error Handling
// ============================================================================

try {
    $result = $notificationService->sendToRecipient(
        $employee,
        'Test Error Handling',
        'Test notifikasi dengan error handling.',
        [],
        ['type' => Notification::TYPE_GENERAL]
    );
    
    if ($result) {
        echo "Notifikasi berhasil dikirim\n";
    } else {
        echo "Notifikasi gagal dikirim\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================================================
// CONTOH 12: Custom Notification Types
// ============================================================================

// Notifikasi gaji
$salaryNotification = $notificationService->sendToRecipient(
    $employee,
    'Gaji Bulan Ini',
    'Gaji Anda untuk bulan ini telah ditransfer ke rekening.',
    [
        'action' => 'view_salary',
        'month' => now()->format('Y-m'),
        'amount' => 5000000,
    ],
    [
        'type' => Notification::TYPE_SALARY,
        'priority' => Notification::PRIORITY_HIGH,
        'image_url' => 'https://example.com/images/salary.jpg',
    ]
);

// Notifikasi sistem
$systemNotification = $notificationService->sendToRecipient(
    $employee,
    'Maintenance Sistem',
    'Sistem akan down untuk maintenance pada pukul 23:00.',
    [
        'action' => 'system_maintenance',
        'start_time' => '23:00',
        'duration' => '2 hours',
    ],
    [
        'type' => Notification::TYPE_SYSTEM,
        'priority' => Notification::PRIORITY_URGENT,
    ]
);

echo "Custom notifications sent successfully\n";

// ============================================================================
// CONTOH 13: Notification dengan Rich Content
// ============================================================================

$richNotification = $notificationService->sendToRecipient(
    $employee,
    'Event Perusahaan',
    'Jangan lewatkan event tahunan perusahaan minggu depan!',
    [
        'action' => 'view_event',
        'event_id' => 123,
        'event_date' => now()->addWeek()->format('Y-m-d'),
        'event_location' => 'Hotel Grand City',
        'event_type' => 'annual_gathering',
    ],
    [
        'type' => Notification::TYPE_ANNOUNCEMENT,
        'priority' => Notification::PRIORITY_HIGH,
        'image_url' => 'https://example.com/images/event.jpg',
        'action_url' => 'https://app.example.com/events/123',
    ]
);

echo "Rich notification sent successfully\n";

// ============================================================================
// CONTOH 14: Performance Testing
// ============================================================================

$startTime = microtime(true);

// Kirim 100 notifikasi test
for ($i = 1; $i <= 100; $i++) {
    $notificationService->sendToRecipient(
        $employee,
        "Test Notification #{$i}",
        "Ini adalah test notification ke-{$i}",
        ['test_id' => $i],
        ['type' => Notification::TYPE_GENERAL]
    );
}

$endTime = microtime(true);
$executionTime = $endTime - $startTime;

echo "Performance test: 100 notifications sent in {$executionTime} seconds\n";

// ============================================================================
// CONTOH 15: Cleanup Test Data
// ============================================================================

// Hapus notifikasi test (opsional)
$testNotifications = Notification::where('title', 'like', 'Test%')->delete();
echo "Test notifications cleaned up: {$testNotifications} deleted\n";

echo "\n=== SELESAI ===\n";
echo "Semua contoh penggunaan sistem notifikasi telah dijalankan.\n";
