<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Continuous Permit Sync Monitor\n";
echo "================================\n\n";

echo "📊 Initial Permit Data:\n";
$permits = \App\Models\Permit::select('id', 'firestore_id', 'jenis_perizinan', 'nama_karyawan', 'tanggal_mulai', 'tanggal_selesai', 'deskripsi', 'status', 'updated_at')->get();
foreach ($permits as $permit) {
    echo "   ID: {$permit->id} | Type: {$permit->jenis_perizinan} | Employee: {$permit->nama_karyawan} | Status: {$permit->status} | Firestore ID: {$permit->firestore_id} | Updated: {$permit->updated_at}\n";
}

echo "\n📡 Starting continuous monitoring...\n";
echo "   - Checking for changes every 10 seconds\n";
echo "   - Press Ctrl+C to stop\n\n";

$firestoreService = new \App\Services\FirestoreService();
$lastCheck = now();

while (true) {
    try {
        echo "🔍 Checking for permit changes at " . now()->format('H:i:s') . "...\n";
        
        // Get current MySQL data
        $currentPermits = \App\Models\Permit::select('id', 'firestore_id', 'jenis_perizinan', 'nama_karyawan', 'tanggal_mulai', 'tanggal_selesai', 'deskripsi', 'status', 'updated_at')->get();
        
        // Get current Firestore data - note: getPerizinan returns ['data' => [...], 'lastDocument' => ...]
        $firestoreResponse = $firestoreService->getPerizinan(1000);
        $firestorePermits = $firestoreResponse['data'] ?? [];
        
        $changesDetected = 0;
        $permitsUpdated = 0;
        $permitsCreated = 0;
        
        foreach ($firestorePermits as $firestorePermit) {
            $firestoreId = $firestorePermit['firestore_id'] ?? null;
            if (!$firestoreId) {
                echo "⚠️  Skip permit without firestore_id\n";
                continue;
            }
            
            $mysqlPermit = $currentPermits->where('firestore_id', $firestoreId)->first();
            
            if ($mysqlPermit) {
                // Check for changes
                $hasChanges = false;
                $changes = [];
                
                // Map Firestore fields to MySQL fields - SESUAIKAN DENGAN STRUKTUR YANG BENAR
                $fieldMappings = [
                    'jenis_perizinan' => 'permitType',      // Firestore: permitType
                    'nama_karyawan' => 'employeeName',       // Firestore: employeeName
                    'tanggal_mulai' => 'startDate',          // Firestore: startDate
                    'tanggal_selesai' => 'endDate',          // Firestore: endDate
                    'deskripsi' => 'description',             // Firestore: description
                    'status' => 'status'                      // Firestore: status
                ];
                
                foreach ($fieldMappings as $mysqlField => $firestoreField) {
                    $mysqlValue = $mysqlPermit->{$mysqlField};
                    $firestoreValue = $firestorePermit[$firestoreField] ?? null;
                    
                    // Handle date fields
                    if (in_array($mysqlField, ['tanggal_mulai', 'tanggal_selesai'])) {
                        if ($mysqlValue && $firestoreValue) {
                            $mysqlDate = \Carbon\Carbon::parse($mysqlValue)->format('Y-m-d');
                            $firestoreDate = \Carbon\Carbon::parse($firestoreValue)->format('Y-m-d');
                            if ($mysqlDate !== $firestoreDate) {
                                $hasChanges = true;
                                $changes[$mysqlField] = [
                                    'from' => $mysqlDate,
                                    'to' => $firestoreDate
                                ];
                            }
                        } elseif ($mysqlValue !== $firestoreValue) {
                            $hasChanges = true;
                            $changes[$mysqlField] = [
                                'from' => $mysqlValue,
                                'to' => $firestoreValue
                            ];
                        }
                    } else {
                        if ($mysqlValue !== $firestoreValue) {
                            $hasChanges = true;
                            $changes[$mysqlField] = [
                                'from' => $mysqlValue,
                                'to' => $firestoreValue
                            ];
                        }
                    }
                }
                
                if ($hasChanges) {
                    $changesDetected++;
                    $permitsUpdated++;
                    echo "🔄 Changes detected for permit {$firestorePermit['permitType']} (Firestore ID: {$firestoreId})!\n";
                    
                    // Update MySQL record - SESUAIKAN DENGAN FIELD MAPPING YANG BENAR
                    $updateData = [
                        'jenis_perizinan' => $firestorePermit['permitType'] ?? null,
                        'nama_karyawan' => $firestorePermit['employeeName'] ?? null,
                        'tanggal_mulai' => !empty($firestorePermit['startDate']) ? $firestorePermit['startDate'] : null,
                        'tanggal_selesai' => !empty($firestorePermit['endDate']) ? $firestorePermit['endDate'] : null,
                        'deskripsi' => $firestorePermit['description'] ?? null,
                        'status' => $firestorePermit['status'] ?? null,
                    ];
                    
                    $mysqlPermit->update($updateData);
                    
                    echo "✅ Permit updated!\n";
                    echo "📝 Changes:\n";
                    foreach ($changes as $field => $change) {
                        echo "   {$field}: '{$change['from']}' → '{$change['to']}'\n";
                    }
                    echo "\n";
                    
                    // Refresh the permit data
                    $mysqlPermit->refresh();
                    echo "📊 Updated: {$mysqlPermit->jenis_perizinan} | Status: {$mysqlPermit->status} | Updated at: {$mysqlPermit->updated_at}\n\n";
                }
            } else {
                // Create new permit
                $changesDetected++;
                $permitsCreated++;
                echo "🆕 New permit detected: {$firestorePermit['permitType']} (Firestore ID: {$firestoreId})\n";
                
                $createData = [
                    'firestore_id' => $firestoreId,
                    'jenis_perizinan' => $firestorePermit['permitType'] ?? null,
                    'nama_karyawan' => $firestorePermit['employeeName'] ?? null,
                    'tanggal_mulai' => !empty($firestorePermit['startDate']) ? $firestorePermit['startDate'] : null,
                    'tanggal_selesai' => !empty($firestorePermit['endDate']) ? $firestorePermit['endDate'] : null,
                    'deskripsi' => $firestorePermit['description'] ?? null,
                    'status' => $firestorePermit['status'] ?? null,
                    'uid' => $firestorePermit['uid'] ?? null,
                ];
                
                $newPermit = \App\Models\Permit::create($createData);
                echo "✅ New permit created in MySQL (ID: {$newPermit->id})\n\n";
            }
        }
        
        if ($changesDetected === 0) {
            echo "✅ No permit changes detected\n";
        } else {
            echo "📊 Summary: {$changesDetected} changes, {$permitsUpdated} updated, {$permitsCreated} created\n";
        }
        
        echo "⏰ Next check in 10 seconds...\n\n";
        
        // Wait 10 seconds
        sleep(10);
        
    } catch (\Exception $e) {
        echo "❌ Error during permit sync check: " . $e->getMessage() . "\n";
        echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "⏰ Retrying in 10 seconds...\n\n";
        sleep(10);
    }
}
