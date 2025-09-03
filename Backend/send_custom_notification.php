<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "📱 Script Notifikasi Custom untuk Teman Anda\n";
echo "==========================================\n\n";

// Get command line arguments
$title = $argv[1] ?? null;
$body = $argv[2] ?? null;
$employeeName = $argv[3] ?? 'Bima Cakra Bara Karebet';

if (!$title || !$body) {
    echo "❌ Usage: php send_custom_notification.php \"Judul Notifikasi\" \"Isi Pesan\" [Nama Employee]\n\n";
    echo "📝 Contoh:\n";
    echo "   php send_custom_notification.php \"Halo! 👋\" \"Ada kabar apa nih?\"\n";
    echo "   php send_custom_notification.php \"Meeting Tim 🚨\" \"Meeting dalam 30 menit!\" \"Bima\"\n\n";
    exit(1);
}

try {
    $notificationService = new \App\Services\NotificationService();
    
    // Find employee by name
    $employee = \App\Models\Employee::where('name', 'like', "%{$employeeName}%")->first();
    
    if (!$employee) {
        echo "❌ Employee dengan nama '{$employeeName}' tidak ditemukan\n";
        echo "📋 Daftar employee yang tersedia:\n";
        
        $allEmployees = \App\Models\Employee::all();
        foreach ($allEmployees as $emp) {
            echo "   - {$emp->name} (UID: {$emp->uid})\n";
        }
        exit(1);
    }
    
    echo "👤 Target: {$employee->name}\n";
    echo "🆔 UID: {$employee->uid}\n";
    echo "📧 Email: {$employee->email}\n\n";
    
    echo "📤 Mengirim Notifikasi:\n";
    echo "   📝 Judul: {$title}\n";
    echo "   💬 Pesan: {$body}\n\n";
    
    // Send notification
    $result = $notificationService->sendToEmployeeWithFirestoreTokens(
        $employee->uid,
        $title,
        $body,
        [
            'type' => 'custom_notification',
            'timestamp' => now()->toISOString(),
            'sender' => 'System Admin',
            'custom_data' => 'Sent via custom script'
        ]
    );
    
    if ($result) {
        echo "✅ Notifikasi berhasil dikirim!\n";
        echo "📱 Device teman Anda akan menerima push notification\n";
        echo "🔔 Pastikan device terhubung internet dan aplikasi terbuka\n";
        
        // Show notification details
        echo "\n📊 Detail Notifikasi:\n";
        echo "   🆔 Employee: {$employee->name}\n";
        echo "   📝 Title: {$title}\n";
        echo "   💬 Body: {$body}\n";
        echo "   ⏰ Waktu: " . now()->format('Y-m-d H:i:s') . "\n";
        echo "   📍 Status: TERKIRIM\n";
        
    } else {
        echo "❌ Notifikasi gagal dikirim\n";
        echo "🔍 Periksa:\n";
        echo "   - Koneksi internet\n";
        echo "   - Konfigurasi FCM\n";
        echo "   - Status FCM token\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

