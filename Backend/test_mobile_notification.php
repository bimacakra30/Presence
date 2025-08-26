<?php
/**
 * Script Test Notifikasi untuk Mobile Developer
 * 
 * Cara pakai:
 * 1. Ganti EMPLOYEE_UID dengan UID dari mobile app
 * 2. Ganti FCM_TOKEN dengan token dari mobile app
 * 3. Jalankan: php test_mobile_notification.php
 */

// Konfigurasi
$EMPLOYEE_UID = 'employee_uid_dari_mobile'; // Ganti dengan UID sebenarnya
$FCM_TOKEN = 'fcm_token_dari_mobile'; // Ganti dengan token sebenarnya
$BACKEND_URL = 'http://localhost:8000'; // Ganti dengan URL backend

// Set timezone
date_default_timezone_set('Asia/Jakarta');

echo "📱 Mobile Notification Test\n";
echo "==========================\n";
echo "Employee UID: $EMPLOYEE_UID\n";
echo "FCM Token: " . substr($FCM_TOKEN, 0, 20) . "...\n";
echo "Backend URL: $BACKEND_URL\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n\n";

// Test scenarios
$tests = [
    [
        'name' => 'Test Manual Notification',
        'command' => "curl -X POST $BACKEND_URL/api/notifications/test-firestore -H 'Content-Type: application/json' -H 'Authorization: Bearer YOUR_AUTH_TOKEN' -d '{\"employee_uid\":\"$EMPLOYEE_UID\",\"title\":\"Test Manual\",\"body\":\"Ini adalah test notifikasi manual\"}'"
    ],
    [
        'name' => 'Test Check-in Reminder (07:30)',
        'command' => "php artisan notifications:automated-presence --type=reminder --time=07:30 --test"
    ],
    [
        'name' => 'Test Check-in Time (08:00)',
        'command' => "php artisan notifications:automated-presence --type=check-in --time=08:00 --test"
    ],
    [
        'name' => 'Test Late Notification (08:15)',
        'command' => "php artisan notifications:automated-presence --type=late --time=08:15 --test"
    ],
    [
        'name' => 'Test Check-out Reminder (16:30)',
        'command' => "php artisan notifications:automated-presence --type=reminder --time=16:30 --test"
    ],
    [
        'name' => 'Test Check-out Time (17:00)',
        'command' => "php artisan notifications:automated-presence --type=check-out --time=17:00 --test"
    ]
];

echo "🧪 Test Scenarios:\n";
echo "==================\n";

foreach ($tests as $index => $test) {
    echo ($index + 1) . ". {$test['name']}\n";
    echo "   Command: {$test['command']}\n\n";
}

echo "📋 Checklist untuk Mobile Developer:\n";
echo "====================================\n";
echo "□ Mobile app sudah setup Firebase\n";
echo "□ FCM token berhasil didapat\n";
echo "□ Token berhasil dikirim ke backend\n";
echo "□ Token tersimpan di Firestore\n";
echo "□ Backend timezone sudah Asia/Jakarta\n";
echo "□ Cron job sudah setup\n\n";

echo "⏰ Jadwal Notifikasi Otomatis:\n";
echo "==============================\n";
echo "07:30 - Reminder Check-in\n";
echo "08:00 - Check-in Time\n";
echo "08:15 - Late Notification\n";
echo "16:30 - Reminder Check-out\n";
echo "17:00 - Check-out Time\n\n";

echo "🔍 Monitoring Commands:\n";
echo "======================\n";
echo "• Monitor real-time: php artisan fcm:live-monitor\n";
echo "• Monitor 24h: php artisan fcm:monitor --period=24\n";
echo "• Cek logs: tail -f storage/logs/laravel.log\n";
echo "• Cek schedule: php artisan schedule:list\n\n";

echo "🚨 Troubleshooting:\n";
echo "==================\n";
echo "• Token tidak valid: php artisan fcm:test-valid-token --employee=51 --token=FCM_TOKEN\n";
echo "• Token tidak tersimpan: cek API endpoint /api/notifications/fcm-tokens/firestore\n";
echo "• Scheduler tidak jalan: cek cron job dengan crontab -l\n";
echo "• Timezone salah: php test_timezone.php\n\n";

echo "📞 Koordinasi dengan Mobile Developer:\n";
echo "=====================================\n";
echo "1. Konfirmasi mobile app siap menerima notifikasi\n";
echo "2. Set waktu server ke waktu test (07:30, 08:00, dll)\n";
echo "3. Jalankan test dan monitor hasil\n";
echo "4. Mobile developer konfirmasi notifikasi diterima\n";
echo "5. Debug jika ada masalah\n\n";

echo "🎯 Expected Results:\n";
echo "===================\n";
echo "✅ Notifikasi diterima tepat waktu\n";
echo "✅ Pesan sesuai dengan jadwal\n";
echo "✅ Data payload lengkap\n";
echo "✅ Notifikasi muncul di notification center\n";
echo "✅ Tap notification membuka app\n\n";

echo "💡 Tips:\n";
echo "=======\n";
echo "• Selalu test dengan --test flag dulu\n";
echo "• Monitor logs untuk debugging\n";
echo "• Koordinasikan waktu test dengan mobile developer\n";
echo "• Pastikan FCM token valid dan up-to-date\n";
?>
