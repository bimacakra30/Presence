<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Models\Employee;

class TestFcmSimpleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:test-simple 
                            {--employee-id=51 : Employee ID to test with}
                            {--token= : FCM token to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simple FCM test without Firestore';

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $employeeId = $this->option('employee-id');
        $fcmToken = $this->option('token');

        $this->info("🧪 Simple FCM Test");
        $this->info("==================");

        // Get employee
        $employee = Employee::find($employeeId);
        if (!$employee) {
            $this->error("❌ Employee dengan ID {$employeeId} tidak ditemukan!");
            return Command::FAILURE;
        }

        $this->info("✅ Employee: {$employee->name} (ID: {$employee->id})");
        $this->info("UID: {$employee->uid}");

        // Use provided token or generate dummy
        if (!$fcmToken) {
            $fcmToken = 'test_fcm_token_' . time() . '_' . $employee->id;
            $this->info("🔑 Generated test token: {$fcmToken}");
        } else {
            $this->info("🔑 Using provided token: {$fcmToken}");
        }

        // Update employee FCM token
        $this->info("📱 Step 1: Update Employee FCM Token");
        try {
            $employee->fcm_token = $fcmToken;
            $employee->save();
            $this->info("✅ FCM token updated in database");
        } catch (\Exception $e) {
            $this->error("❌ Error updating FCM token: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Test send notification
        $this->info("📱 Step 2: Send Test Notification");
        try {
            $result = $this->notificationService->sendToRecipient(
                $employee,
                'Test Notifikasi Sederhana',
                'Ini adalah test notifikasi sederhana tanpa Firestore.',
                [
                    'action' => 'test_simple_notification',
                    'timestamp' => now()->toISOString(),
                    'employee_id' => $employee->id,
                ]
            );

            if ($result) {
                $this->info("✅ Notifikasi berhasil dikirim!");
                $this->info("📱 Cek di mobile app apakah notifikasi diterima");
            } else {
                $this->warn("⚠️ Notifikasi gagal dikirim");
                $this->info("💡 Cek log untuk detail error");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error sending notification: " . $e->getMessage());
        }

        // Show notification statistics
        $this->info("📊 Step 3: Notification Statistics");
        try {
            $stats = $this->notificationService->getStatistics();
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
            $this->error("❌ Error getting statistics: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
