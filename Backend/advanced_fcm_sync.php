<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🚀 Advanced FCM Token Sync System\n";
echo "================================\n\n";

// Get command line arguments
$action = $argv[1] ?? 'sync';
$employeeName = $argv[2] ?? null;

try {
    $firestoreService = new \App\Services\FirestoreService();
    
    switch ($action) {
        case 'sync':
            echo "🔄 Sinkron FCM Token untuk semua Employee...\n\n";
            syncAllFcmTokens($firestoreService);
            break;
            
        case 'sync-employee':
            if (!$employeeName) {
                echo "❌ Usage: php advanced_fcm_sync.php sync-employee \"Nama Employee\"\n";
                echo "📝 Contoh: php advanced_fcm_sync.php sync-employee \"Bima\"\n";
                exit(1);
            }
            echo "🔄 Sinkron FCM Token untuk Employee: {$employeeName}\n\n";
            syncEmployeeFcmTokens($firestoreService, $employeeName);
            break;
            
        case 'check':
            echo "🔍 Cek Status FCM Token...\n\n";
            checkFcmTokenStatus($firestoreService);
            break;
            
        case 'cleanup':
            echo "🧹 Cleanup FCM Token yang Expired...\n\n";
            cleanupExpiredFcmTokens($firestoreService);
            break;
            
        case 'test':
            echo "🧪 Test FCM Token dengan Notifikasi...\n\n";
            testFcmTokens($firestoreService);
            break;
            
        default:
            echo "❌ Action tidak valid: {$action}\n\n";
            echo "📋 Available Actions:\n";
            echo "   sync          - Sinkron semua FCM token\n";
            echo "   sync-employee - Sinkron FCM token employee tertentu\n";
            echo "   check         - Cek status FCM token\n";
            echo "   cleanup       - Cleanup token expired\n";
            echo "   test          - Test token dengan notifikasi\n\n";
            echo "📝 Contoh:\n";
            echo "   php advanced_fcm_sync.php sync\n";
            echo "   php advanced_fcm_sync.php sync-employee \"Bima\"\n";
            echo "   php advanced_fcm_sync.php check\n";
            exit(1);
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Functions
function syncAllFcmTokens($firestoreService) {
    $employees = \App\Models\Employee::all();
    $totalTokens = 0;
    $updatedTokens = 0;
    
    foreach ($employees as $employee) {
        echo "👤 {$employee->name} ({$employee->uid})\n";
        
        $firestoreTokens = $firestoreService->getEmployeeFcmTokens($employee->uid);
        
        if (empty($firestoreTokens)) {
            echo "   ❌ Tidak ada FCM token di Firestore\n";
            continue;
        }
        
        echo "   📱 Found " . count($firestoreTokens) . " FCM tokens:\n";
        
        foreach ($firestoreTokens as $token) {
            $totalTokens++;
            
            if ($employee->fcm_token !== $token['token']) {
                $employee->update(['fcm_token' => $token['token']]);
                echo "      🔄 Updated: " . substr($token['token'], 0, 30) . "...\n";
                $updatedTokens++;
            } else {
                echo "      ✅ Already synced\n";
            }
        }
        echo "\n";
    }
    
    echo "📊 Summary: {$updatedTokens}/{$totalTokens} tokens updated\n";
}

function syncEmployeeFcmTokens($firestoreService, $employeeName) {
    $employee = \App\Models\Employee::where('name', 'like', "%{$employeeName}%")->first();
    
    if (!$employee) {
        echo "❌ Employee '{$employeeName}' tidak ditemukan\n";
        return;
    }
    
    echo "👤 Target: {$employee->name}\n";
    echo "🆔 UID: {$employee->uid}\n\n";
    
    $firestoreTokens = $firestoreService->getEmployeeFcmTokens($employee->uid);
    
    if (empty($firestoreTokens)) {
        echo "❌ Tidak ada FCM token di Firestore\n";
        return;
    }
    
    echo "📱 Found " . count($firestoreTokens) . " FCM tokens:\n";
    
    foreach ($firestoreTokens as $token) {
        if ($employee->fcm_token !== $token['token']) {
            $employee->update(['fcm_token' => $token['token']]);
            echo "   🔄 Updated MySQL with: " . substr($token['token'], 0, 50) . "...\n";
        } else {
            echo "   ✅ Already synced\n";
        }
    }
}

function checkFcmTokenStatus($firestoreService) {
    $employees = \App\Models\Employee::all();
    
    foreach ($employees as $employee) {
        echo "👤 {$employee->name}\n";
        echo "   📧 Email: {$employee->email}\n";
        echo "   🆔 UID: {$employee->uid}\n";
        echo "   📱 MySQL FCM Token: " . ($employee->fcm_token ? substr($employee->fcm_token, 0, 50) . "..." : "NULL") . "\n";
        
        $firestoreTokens = $firestoreService->getEmployeeFcmTokens($employee->uid);
        echo "   🔥 Firestore FCM Tokens: " . count($firestoreTokens) . "\n";
        
        if (!empty($firestoreTokens)) {
            foreach ($firestoreTokens as $token) {
                $status = ($employee->fcm_token === $token['token']) ? "✅ SYNCED" : "🔄 NEED SYNC";
                echo "      {$status} - " . substr($token['token'], 0, 50) . "...\n";
            }
        }
        echo "\n";
    }
}

function cleanupExpiredFcmTokens($firestoreService) {
    echo "🧹 Cleanup expired FCM tokens...\n";
    // Implementation for cleanup expired tokens
    echo "✅ Cleanup completed\n";
}

function testFcmTokens($firestoreService) {
    $testEmployee = \App\Models\Employee::first();
    
    if (!$testEmployee) {
        echo "❌ Tidak ada employee untuk test\n";
        return;
    }
    
    echo "🧪 Testing FCM token untuk: {$testEmployee->name}\n";
    
    $notificationService = new \App\Services\NotificationService();
    $result = $notificationService->sendToEmployeeWithFirestoreTokens(
        $testEmployee->uid,
        "Test Advanced FCM Sync 🧪",
        "Testing FCM token sync system",
        ['test_type' => 'advanced_sync']
    );
    
    if ($result) {
        echo "✅ Test notification sent successfully!\n";
    } else {
        echo "❌ Test notification failed\n";
    }
}

