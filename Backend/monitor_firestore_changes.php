<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Monitoring Firestore Changes for Employee Sync\n";
echo "===============================================\n\n";

echo "📊 Current Employee Data:\n";
$employees = \App\Models\Employee::select('id', 'name', 'email', 'uid', 'updated_at')->get();
foreach ($employees as $emp) {
    echo "   ID: {$emp->id} | Name: {$emp->name} | Email: {$emp->email} | UID: {$emp->uid} | Updated: {$emp->updated_at}\n";
}

echo "\n🎧 Listener Status:\n";
$listenerService = new \App\Services\FirestoreChangeListenerService(
    new \App\Services\FirestoreService(),
    new \App\Services\RealTimeEmployeeSyncService(new \App\Services\FirestoreService())
);

$listenerStatus = $listenerService->getListenerStatus();
$listenerHealth = $listenerService->healthCheck();

echo "   Is Listening: " . ($listenerStatus['is_listening'] ? 'Yes' : 'No') . "\n";
echo "   Cache Status: " . ($listenerStatus['cache_status'] ? 'Active' : 'Inactive') . "\n";
echo "   Health Status: {$listenerHealth['status']}\n";
echo "   Message: {$listenerHealth['message']}\n";

echo "\n📡 Monitoring for Firestore changes...\n";
echo "   - Any changes in Firestore will trigger sync\n";
echo "   - Check Laravel logs for sync activities\n";
echo "   - Press Ctrl+C to stop monitoring\n\n";

echo "🔍 To check for changes, run:\n";
echo "   tail -f storage/logs/laravel.log | grep -E \"(Firestore|Event-driven|Employee|Change)\"\n\n";

echo "🧪 To test sync manually:\n";
echo "   php artisan employee:sync-realtime --simulate-change\n\n";

echo "📊 To check current status:\n";
echo "   php artisan employee:sync-realtime --listener-status\n\n";

echo "⏰ Monitoring started at: " . now()->format('Y-m-d H:i:s') . "\n";
echo "   Waiting for Firestore changes...\n\n";

// Keep script running to monitor
while (true) {
    // Check for recent changes every 10 seconds
    sleep(10);
    
    // Get latest employee data
    $latestEmployees = \App\Models\Employee::select('id', 'name', 'email', 'uid', 'updated_at')->get();
    
    // Check if any employee was updated
    foreach ($latestEmployees as $latestEmp) {
        $originalEmp = $employees->where('id', $latestEmp->id)->first();
        if ($originalEmp && $latestEmp->updated_at->gt($originalEmp->updated_at)) {
            echo "🔄 Change detected at " . now()->format('H:i:s') . ":\n";
            echo "   Employee: {$latestEmp->name} (ID: {$latestEmp->id})\n";
            echo "   Updated: {$latestEmp->updated_at}\n";
            echo "   Previous: {$originalEmp->updated_at}\n\n";
            
            // Update original data
            $employees = $latestEmployees;
        }
    }
    
    // Show heartbeat
    echo "💓 Heartbeat: " . now()->format('H:i:s') . " - Monitoring active...\n";
}

