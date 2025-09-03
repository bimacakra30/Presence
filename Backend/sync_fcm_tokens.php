<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔄 Sinkron FCM Token Terbaru dari Firestore...\n\n";

try {
    $firestoreService = new \App\Services\FirestoreService();
    
    // Get all employees
    $employees = \App\Models\Employee::all();
    
    $totalTokens = 0;
    $updatedTokens = 0;
    $newTokens = 0;
    
    foreach ($employees as $employee) {
        echo "👤 Employee: {$employee->name} ({$employee->uid})\n";
        
        // Get current FCM tokens from Firestore
        $firestoreTokens = $firestoreService->getEmployeeFcmTokens($employee->uid);
        
        if (empty($firestoreTokens)) {
            echo "   ❌ Tidak ada FCM token di Firestore\n";
            continue;
        }
        
        echo "   📱 Found " . count($firestoreTokens) . " FCM tokens in Firestore:\n";
        
        foreach ($firestoreTokens as $token) {
            $totalTokens++;
            echo "      - Token: " . substr($token['token'], 0, 50) . "...\n";
            echo "        ID: {$token['id']}\n";
            
            // Check if this token is different from MySQL
            if ($employee->fcm_token !== $token['token']) {
                // Update MySQL with the latest token
                $employee->update(['fcm_token' => $token['token']]);
                echo "        🔄 Updated MySQL FCM token\n";
                $updatedTokens++;
            } else {
                echo "        ✅ Token sudah sama dengan MySQL\n";
            }
        }
        
        echo "\n";
    }
    
    echo "✅ Sinkron FCM Token selesai!\n\n";
    
    // Summary
    echo "📊 Hasil Sinkronisasi:\n";
    echo "   📱 Total FCM tokens: {$totalTokens}\n";
    echo "   🔄 Updated tokens: {$updatedTokens}\n";
    echo "   ✅ No changes needed: " . ($totalTokens - $updatedTokens) . "\n";
    
    // Show final status
    echo "\n📋 Status FCM Token Setelah Sinkron:\n";
    $finalEmployees = \App\Models\Employee::select('name', 'fcm_token')->get();
    foreach ($finalEmployees as $emp) {
        $tokenStatus = $emp->fcm_token ? '✅ Token Updated' : '❌ No Token';
        echo "   {$emp->name}: {$tokenStatus}\n";
    }
    
    echo "\n💡 Tips:\n";
    echo "   - Jalankan script ini setiap kali ada update FCM token\n";
    echo "   - FCM token akan otomatis diupdate ke MySQL\n";
    echo "   - Sistem akan menggunakan token terbaru untuk notifikasi\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

