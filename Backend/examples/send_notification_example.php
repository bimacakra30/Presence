<?php

/**
 * Contoh Penggunaan Sistem Notifikasi FCM
 * 
 * File ini menunjukkan cara menggunakan NotificationService
 * untuk mengirim notifikasi push ke mobile app
 */

use App\Services\NotificationService;
use App\Models\Employee;
use App\Models\Notification;

// Inisialisasi service
$notificationService = new NotificationService();

// ============================================================================
// CONTOH 1: Kirim Notifikasi ke Satu Karyawan
// ============================================================================

$employee = Employee::find(1); // Ganti dengan ID karyawan yang sesuai

if ($employee && $employee->fcm_token) {
    $result = $notificationService->sendToRecipient(
        $employee,
        'Selamat Pagi!',
        'Selamat datang di kantor hari ini. Jangan lupa untuk check-in.',
        [
            'action' => 'check_in',
            'date' => now()->format('Y-m-d'),
            'location' => 'Kantor Pusat',
        ],
        [
            'type' => Notification::TYPE_PRESENCE,
            'priority' => Notification::PRIORITY_NORMAL,
            'image_url' => 'https://example.com/images/welcome.jpg',
            'action_url' => 'https://app.example.com/check-in',
        ]
    );

    if ($result) {
        echo "✅ Notifikasi berhasil dikirim ke {$employee->name}\n";
    } else {
        echo "❌ Gagal mengirim notifikasi ke {$employee->name}\n";
    }
} else {
    echo "⚠️ Karyawan tidak ditemukan atau tidak memiliki FCM token\n";
}

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

echo "📢 Notifikasi ke semua karyawan: " . count($result) . " berhasil dikirim\n";

// ============================================================================
// CONTOH 3: Kirim Notifikasi Terjadwal
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

if ($notification) {
    echo "📅 Notifikasi terjadwal dibuat dengan ID: {$notification->id}\n";
    echo "⏰ Dijadwalkan untuk: {$scheduledTime->format('Y-m-d H:i:s')}\n";
}

// ============================================================================
// CONTOH 4: Update FCM Token
// ============================================================================

$fcmToken = 'sample_fcm_token_from_mobile_app';
$notificationService->updateFcmToken($employee, $fcmToken);

echo "🔑 FCM token berhasil diupdate\n";

// ============================================================================
// CONTOH 5: Dapatkan Statistik Notifikasi
// ============================================================================

$stats = $notificationService->getStatistics();

echo "📊 Statistik Notifikasi:\n";
echo "- Total: " . $stats['total'] . "\n";
echo "- Terkirim: " . $stats['sent'] . "\n";
echo "- Pending: " . $stats['pending'] . "\n";
echo "- Gagal: " . $stats['failed'] . "\n";
echo "- Terjadwal: " . $stats['scheduled'] . "\n";
echo "- Belum dibaca: " . $stats['unread'] . "\n";

echo "\n=== SELESAI ===\n";
echo "Semua contoh penggunaan sistem notifikasi telah dijalankan.\n";
