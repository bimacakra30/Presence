<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Services\FirestoreService;
use App\Models\Employee;
use App\Models\Notification;

class TestFcmWithFirestoreTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:test-firestore-tokens 
                            {--employee-uid= : Employee UID to test with}
                            {--all : Test with all employees}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test FCM notification dengan tokens dari Firestore';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService, FirestoreService $firestoreService)
    {
        $this->info('🧪 Testing FCM dengan Tokens dari Firestore...');
        $this->newLine();

        if ($this->option('all')) {
            $this->testAllEmployees($notificationService, $firestoreService);
        } else {
            $employeeUid = $this->option('employee-uid');
            
            if (!$employeeUid) {
                $this->error("❌ Employee UID tidak diberikan!");
                $this->info("💡 Gunakan: --employee-uid=your_employee_uid");
                $this->info("💡 Atau gunakan: --all untuk test semua employee");
                return Command::FAILURE;
            }

            $this->testSingleEmployee($notificationService, $firestoreService, $employeeUid);
        }

        return Command::SUCCESS;
    }

    protected function testSingleEmployee($notificationService, $firestoreService, $employeeUid)
    {
        $this->info("📱 Testing Employee UID: {$employeeUid}");
        $this->newLine();

        // Get FCM tokens from Firestore
        $this->info('📱 Step 1: Get FCM Tokens from Firestore');
        try {
            $fcmTokens = $firestoreService->getEmployeeFcmTokens($employeeUid);
            
            if (empty($fcmTokens)) {
                $this->warn("⚠️ Tidak ada FCM tokens ditemukan untuk employee UID: {$employeeUid}");
                $this->info("💡 Pastikan employee memiliki fcmTokens subcollection di Firestore");
                return;
            }

            $this->info("✅ Ditemukan " . count($fcmTokens) . " FCM tokens");
            
            foreach ($fcmTokens as $index => $token) {
                $this->info("   Token " . ($index + 1) . ": " . substr($token['token'], 0, 20) . "...");
                $this->info("   Device: " . ($token['device_id'] ?? 'Unknown') . " (" . $token['platform'] . ")");
            }
        } catch (\Exception $e) {
            $this->error('❌ Error getting FCM tokens: ' . $e->getMessage());
            return;
        }
        $this->newLine();

        // Test send notification
        $this->info('📱 Step 2: Send Test Notification');
        try {
            $result = $notificationService->sendToEmployeeWithFirestoreTokens(
                $employeeUid,
                'Test Notifikasi dari Firestore',
                'Ini adalah test notifikasi yang menggunakan FCM tokens dari Firestore.',
                [
                    'action' => 'test_firestore_notification',
                    'timestamp' => now()->toISOString(),
                    'source' => 'firestore_test',
                    'employee_uid' => $employeeUid,
                ],
                [
                    'type' => Notification::TYPE_GENERAL,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );

            if ($result) {
                $this->info('✅ Notifikasi berhasil dikirim menggunakan tokens dari Firestore!');
                $this->info('📱 Cek di mobile app apakah notifikasi diterima');
            } else {
                $this->warn('⚠️ Notifikasi gagal dikirim');
                $this->info('💡 Cek log untuk detail error');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error sending notification: ' . $e->getMessage());
        }
        $this->newLine();

        // Test high priority notification
        $this->info('📱 Step 3: Send High Priority Notification');
        try {
            $result = $notificationService->sendToEmployeeWithFirestoreTokens(
                $employeeUid,
                'PENGUMUMAN PENTING (Firestore)',
                'Ini adalah notifikasi dengan prioritas tinggi menggunakan tokens dari Firestore.',
                [
                    'action' => 'urgent_firestore_announcement',
                    'priority' => 'high',
                    'timestamp' => now()->toISOString(),
                ],
                [
                    'type' => Notification::TYPE_ANNOUNCEMENT,
                    'priority' => Notification::PRIORITY_HIGH,
                ]
            );

            if ($result) {
                $this->info('✅ High priority notification sent successfully!');
            } else {
                $this->warn('⚠️ High priority notification failed to send');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error sending high priority notification: ' . $e->getMessage());
        }
    }

    protected function testAllEmployees($notificationService, $firestoreService)
    {
        $this->info('📱 Testing All Employees with Firestore Tokens');
        $this->newLine();

        // Get all active FCM tokens
        $this->info('📱 Step 1: Get All Active FCM Tokens from Firestore');
        try {
            $allTokens = $firestoreService->getAllActiveFcmTokens();
            
            if (empty($allTokens)) {
                $this->warn("⚠️ Tidak ada active FCM tokens ditemukan di Firestore");
                $this->info("💡 Pastikan employees memiliki fcmTokens subcollection");
                return;
            }

            $this->info("✅ Ditemukan " . count($allTokens) . " active FCM tokens dari " . count(array_unique(array_column($allTokens, 'employee_uid'))) . " employees");
            
            // Group by employee
            $employeeTokens = [];
            foreach ($allTokens as $token) {
                $employeeUid = $token['employee_uid'];
                if (!isset($employeeTokens[$employeeUid])) {
                    $employeeTokens[$employeeUid] = [
                        'name' => $token['employee_name'],
                        'email' => $token['employee_email'],
                        'tokens' => []
                    ];
                }
                $employeeTokens[$employeeUid]['tokens'][] = $token;
            }

            foreach ($employeeTokens as $employeeUid => $data) {
                $this->info("   {$data['name']} ({$data['email']}): " . count($data['tokens']) . " tokens");
            }
        } catch (\Exception $e) {
            $this->error('❌ Error getting all FCM tokens: ' . $e->getMessage());
            return;
        }
        $this->newLine();

        // Test send notification to all
        $this->info('📱 Step 2: Send Test Notification to All Employees');
        try {
            $results = $notificationService->sendToAllEmployeesWithFirestoreTokens(
                'Test Notifikasi Massal dari Firestore',
                'Ini adalah test notifikasi massal yang menggunakan FCM tokens dari Firestore.',
                [
                    'action' => 'test_mass_notification',
                    'timestamp' => now()->toISOString(),
                    'source' => 'firestore_mass_test',
                ],
                [
                    'type' => Notification::TYPE_ANNOUNCEMENT,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );

            $successCount = count(array_filter($results, function($result) {
                return $result['success'];
            }));

            $this->info("✅ Mass notification sent: {$successCount} dari " . count($results) . " berhasil");
            
            if ($successCount > 0) {
                $this->info('📱 Cek di mobile apps apakah notifikasi diterima');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error sending mass notification: ' . $e->getMessage());
        }
        $this->newLine();

        // Show statistics
        $this->info('📊 Step 3: Notification Statistics');
        try {
            $stats = $notificationService->getStatistics();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total', $stats['total']],
                    ['Sent', $stats['sent']],
                    ['Pending', $stats['pending']],
                    ['Failed', $stats['failed']],
                    ['Scheduled', $stats['scheduled']],
                    ['Unread', $stats['unread']],
                ]
            );
        } catch (\Exception $e) {
            $this->error('❌ Error getting statistics: ' . $e->getMessage());
        }
    }
}
