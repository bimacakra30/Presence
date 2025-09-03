<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Test Name Change Sync - Event-Driven System\n";
echo "=============================================\n\n";

try {
    $syncService = new \App\Services\RealTimeEmployeeSyncService(
        new \App\Services\FirestoreService()
    );
    
    // Get first employee for testing
    $employee = \App\Models\Employee::first();
    
    if (!$employee) {
        echo "❌ No employees found for testing\n";
        exit(1);
    }
    
    echo "👤 Test Employee:\n";
    echo "   ID: {$employee->id}\n";
    echo "   Current Name: {$employee->name}\n";
    echo "   Email: {$employee->email}\n";
    echo "   UID: {$employee->uid}\n";
    echo "   Last Updated: {$employee->updated_at}\n\n";
    
    // Simulate name change in Firestore
    echo "🔄 Simulating name change in Firestore...\n";
    
    $newName = "Test Name Changed " . time();
    $firestoreData = [
        'name' => $newName,
        'email' => $employee->email,
        'status' => $employee->status,
        'position' => $employee->position
    ];
    
    echo "   New Name: {$newName}\n";
    echo "   Simulating UPDATE change...\n\n";
    
    // Trigger sync with name change
    $result = $syncService->syncOnFirestoreChange('UPDATE', $employee->uid, $firestoreData);
    
    echo "📊 Sync Result:\n";
    echo "   Status: {$result['status']}\n";
    echo "   Action: {$result['action']}\n";
    
    if (isset($result['employee'])) {
        echo "   Employee ID: {$result['employee']->id}\n";
        echo "   Employee Name: {$result['employee']->name}\n";
        echo "   Updated At: {$result['employee']->updated_at}\n";
    }
    
    echo "\n";
    
    // Check if employee was actually updated in MySQL
    $updatedEmployee = \App\Models\Employee::find($employee->id);
    
    echo "📋 Database Check:\n";
    echo "   Original Name: {$employee->name}\n";
    echo "   Updated Name: {$updatedEmployee->name}\n";
    echo "   Original Updated: {$employee->updated_at}\n";
    echo "   New Updated: {$updatedEmployee->updated_at}\n";
    
    if ($updatedEmployee->name !== $employee->name) {
        echo "   ✅ Name change detected in database!\n";
        echo "   ✅ Sync successful!\n";
    } else {
        echo "   ❌ No name change detected in database\n";
        echo "   ❌ Sync may have failed\n";
    }
    
    echo "\n🔍 To monitor real-time changes:\n";
    echo "   tail -f storage/logs/laravel.log | grep -E \"(Event-driven|Employee updated|Change detected)\"\n\n";
    
    echo "🎉 Test completed!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

