<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Models\Employee;
use App\Models\Notification;

class TestFcmWithValidTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:test-valid-token 
                            {--employee=51 : Employee ID to test with}
                            {--token= : FCM token yang valid untuk testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test FCM notification dengan token yang valid';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService)
    {
        $this->info('🧪 Testing FCM dengan Token Valid...');
        $this->newLine();

        // Get employee
        $employeeId = $this->option('employee');
        $employee = Employee::find($employeeId);

        if (!$employee) {
            $this->error("❌ Employee dengan ID {$employeeId} tidak ditemukan!");
            return Command::FAILURE;
        }

        $this->info("✅ Using employee: {$employee->name} (ID: {$employee->id})");
        $this->newLine();

        // Get FCM token
        $fcmToken = $this->option('token');
        
        if (!$fcmToken) {
            $this->error("❌ FCM token tidak diberikan!");
            $this->info("💡 Gunakan: --token=your_valid_fcm_token");
            $this->info("💡 Dapatkan token dari Firebase Console > Cloud Messaging > FCM registration tokens");
            return Command::FAILURE;
        }

        // Update FCM token
        $this->info('📱 Step 1: Update FCM Token');
        try {
            $notificationService->updateFcmToken($employee, $fcmToken);
            $this->info('✅ FCM token updated successfully!');
            $this->info("🔑 Token: " . substr($fcmToken, 0, 20) . "...");
        } catch (\Exception $e) {
            $this->error('❌ Error updating FCM token: ' . $e->getMessage());
            return Command::FAILURE;
        }
        $this->newLine();

        // Test send notification
        $this->info('📱 Step 2: Send Test Notification');
        try {
            $result = $notificationService->sendToRecipient(
                $employee,
                'Test Notifikasi dari Backend',
                'Ini adalah test notifikasi yang dikirim dari backend website dengan token valid.',
                [
                    'action' => 'test_notification',
                    'timestamp' => now()->toISOString(),
                    'source' => 'backend_website',
                    'employee_id' => $employee->id,
                ],
                [
                    'type' => Notification::TYPE_GENERAL,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );

            if ($result) {
                $this->info('✅ Notifikasi berhasil dikirim!');
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
            $result = $notificationService->sendToRecipient(
                $employee,
                'PENGUMUMAN PENTING',
                'Ini adalah notifikasi dengan prioritas tinggi untuk testing.',
                [
                    'action' => 'urgent_announcement',
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
        $this->newLine();

        // Show statistics
        $this->info('📊 Step 4: Notification Statistics');
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

        $this->newLine();
        $this->info('🎉 Testing completed!');
        $this->info('💡 Jika notifikasi berhasil dikirim, berarti FCM sudah berfungsi dengan baik');
        $this->info('📱 Mobile app developer dapat menggunakan endpoint ini untuk update FCM token');

        return Command::SUCCESS;
    }
}
