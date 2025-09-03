<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Active Firestore Sync\n";
echo "================================\n\n";

try {
    // Get current data
    echo "📊 Current Data:\n";
    $mysqlEmp = \App\Models\Employee::where('uid', 'a3729a4a-7797-4139-a597-bfd434892be5')->first();
    echo "   MySQL Name: {$mysqlEmp->name}\n";
    echo "   MySQL Updated: {$mysqlEmp->updated_at}\n\n";
    
    // Get Firestore data
    echo "🔥 Firestore Data:\n";
    $firestoreService = new \App\Services\FirestoreService();
    $firestoreData = $firestoreService->getUser('a3729a4a-7797-4139-a597-bfd434892be5');
    echo "   Firestore Name: " . ($firestoreData['name'] ?? 'N/A') . "\n\n";
    
    // Check if there are changes
    $hasChanges = false;
    $changes = [];
    
    $fieldsToCheck = ['name', 'email', 'phone', 'address', 'status', 'position'];
    foreach ($fieldsToCheck as $field) {
        $mysqlValue = $mysqlEmp->{$field};
        $firestoreValue = $firestoreData[$field] ?? null;
        
        if ($mysqlValue !== $firestoreValue) {
            $hasChanges = true;
            $changes[$field] = [
                'from' => $mysqlValue,
                'to' => $firestoreValue
            ];
        }
    }
    
    if ($hasChanges) {
        echo "🔄 Changes detected! Syncing...\n";
        
        // Map Firestore data to MySQL format
        $updateData = [
            'name' => $firestoreData['name'] ?? null,
            'email' => $firestoreData['email'] ?? null,
            'phone' => $firestoreData['phone'] ?? null,
            'address' => $firestoreData['address'] ?? null,
            'status' => $firestoreData['status'] ?? null,
            'position' => $firestoreData['position'] ?? null,
            'profile_picture_url' => $firestoreData['profilePictureUrl'] ?? null,
            'date_of_birth' => !empty($firestoreData['dateOfBirth']) ? $firestoreData['dateOfBirth'] : null,
        ];
        
        // Update MySQL record
        $mysqlEmp->update($updateData);
        
        echo "✅ Sync completed!\n";
        echo "📝 Changes made:\n";
        foreach ($changes as $field => $change) {
            echo "   {$field}: '{$change['from']}' → '{$change['to']}'\n";
        }
        
        // Verify the update
        $mysqlEmp->refresh();
        echo "\n📊 Updated MySQL Data:\n";
        echo "   MySQL Name: {$mysqlEmp->name}\n";
        echo "   MySQL Updated: {$mysqlEmp->updated_at}\n";
        
    } else {
        echo "✅ No changes detected. Data is already in sync.\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

