<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Continuous Firestore Sync Monitor\n";
echo "===================================\n\n";

echo "📊 Initial Employee Data:\n";
$employees = \App\Models\Employee::select('id', 'name', 'email', 'uid', 'updated_at')->get();
foreach ($employees as $emp) {
    echo "   ID: {$emp->id} | Name: {$emp->name} | UID: {$emp->uid} | Updated: {$emp->updated_at}\n";
}

echo "\n📡 Starting continuous monitoring...\n";
echo "   - Checking for changes every 10 seconds\n";
echo "   - Press Ctrl+C to stop\n\n";

$firestoreService = new \App\Services\FirestoreService();
$lastCheck = now();

while (true) {
    try {
        echo "🔍 Checking for changes at " . now()->format('H:i:s') . "...\n";
        
        // Get current MySQL data
        $currentEmployees = \App\Models\Employee::select('id', 'name', 'email', 'uid', 'updated_at')->get();
        
        // Get current Firestore data
        $firestoreEmployees = $firestoreService->getUsersMinimal(false); // No cache
        
        $changesDetected = 0;
        
        foreach ($firestoreEmployees as $firestoreEmp) {
            $uid = $firestoreEmp['uid'];
            $mysqlEmp = $currentEmployees->where('uid', $uid)->first();
            
            if ($mysqlEmp) {
                // Check for changes
                $hasChanges = false;
                $changes = [];
                
                $fieldsToCheck = ['name', 'email', 'phone', 'address', 'status', 'position'];
                foreach ($fieldsToCheck as $field) {
                    $mysqlValue = $mysqlEmp->{$field};
                    $firestoreValue = $firestoreEmp[$field] ?? null;
                    
                    if ($mysqlValue !== $firestoreValue) {
                        $hasChanges = true;
                        $changes[$field] = [
                            'from' => $mysqlValue,
                            'to' => $firestoreValue
                        ];
                    }
                }
                
                if ($hasChanges) {
                    $changesDetected++;
                    echo "🔄 Changes detected for {$firestoreEmp['name']} (UID: {$uid})!\n";
                    
                    // Update MySQL record
                    $updateData = [
                        'name' => $firestoreEmp['name'] ?? null,
                        'email' => $firestoreEmp['email'] ?? null,
                        'phone' => $firestoreEmp['phone'] ?? null,
                        'address' => $firestoreEmp['address'] ?? null,
                        'status' => $firestoreEmp['status'] ?? null,
                        'position' => $firestoreEmp['position'] ?? null,
                        'profile_picture_url' => $firestoreEmp['profilePictureUrl'] ?? null,
                        'date_of_birth' => !empty($firestoreEmp['dateOfBirth']) ? $firestoreEmp['dateOfBirth'] : null,
                    ];
                    
                    $mysqlEmp->update($updateData);
                    
                    echo "✅ Sync completed!\n";
                    echo "📝 Changes:\n";
                    foreach ($changes as $field => $change) {
                        echo "   {$field}: '{$change['from']}' → '{$change['to']}'\n";
                    }
                    echo "\n";
                    
                    // Refresh the employee data
                    $mysqlEmp->refresh();
                    echo "📊 Updated: {$mysqlEmp->name} | Updated at: {$mysqlEmp->updated_at}\n\n";
                }
            }
        }
        
        if ($changesDetected === 0) {
            echo "✅ No changes detected\n";
        }
        
        echo "⏰ Next check in 10 seconds...\n\n";
        
        // Wait 10 seconds
        sleep(10);
        
    } catch (\Exception $e) {
        echo "❌ Error during sync check: " . $e->getMessage() . "\n";
        echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "⏰ Retrying in 10 seconds...\n\n";
        sleep(10);
    }
}

