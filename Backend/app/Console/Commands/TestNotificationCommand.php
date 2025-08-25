<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Models\Employee;
use App\Models\Notification;

class TestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test {--employee=1 : Employee ID to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test notification system with various scenarios';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService)
    {
        $this->info('🧪 Testing Notification System...');
        $this->newLine();

        // Get employee
        $employeeId = $this->option('employee');
        $employee = Employee::find($employeeId);

        if (!$employee) {
            $this->error("❌ Employee with ID {$employeeId} not found!");
            return Command::FAILURE;
        }

        $this->info("✅ Using employee: {$employee->name} (ID: {$employee->id})");
        $this->newLine();

        // Test 0: Update FCM token first
        $this->info('📱 Test 0: Update FCM Token (Required for push notifications)');
        try {
            $testToken = 'test_fcm_token_' . time();
            $notificationService->updateFcmToken($employee, $testToken);
            $this->info('✅ FCM token updated successfully!');
            $this->info("🔑 Token: {$testToken}");
        } catch (\Exception $e) {
            $this->error('❌ Error updating FCM token: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 1: Basic notification
        $this->info('📱 Test 1: Basic Notification');
        try {
            $result = $notificationService->sendToRecipient(
                $employee,
                'Test Notification',
                'Ini adalah test notifikasi dari sistem.',
                [
                    'action' => 'test',
                    'timestamp' => now()->toISOString(),
                ],
                [
                    'type' => Notification::TYPE_GENERAL,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );

            if ($result) {
                $this->info('✅ Basic notification sent successfully!');
            } else {
                $this->warn('⚠️ Basic notification failed to send');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error sending basic notification: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 2: Presence notification
        $this->info('📱 Test 2: Presence Notification');
        try {
            $result = $notificationService->sendToRecipient(
                $employee,
                'Check-in Berhasil',
                'Anda telah berhasil check-in pada ' . now()->format('H:i'),
                [
                    'presence_id' => 123,
                    'action' => 'view_presence',
                    'date' => now()->format('Y-m-d'),
                ],
                [
                    'type' => Notification::TYPE_PRESENCE,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );

            if ($result) {
                $this->info('✅ Presence notification sent successfully!');
            } else {
                $this->warn('⚠️ Presence notification failed to send');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error sending presence notification: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 3: High priority notification
        $this->info('📱 Test 3: High Priority Notification');
        try {
            $result = $notificationService->sendToRecipient(
                $employee,
                'PENGUMUMAN PENTING',
                'Ini adalah notifikasi dengan prioritas tinggi!',
                [
                    'action' => 'announcement',
                    'priority' => 'high',
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

        // Test 4: Scheduled notification
        $this->info('📱 Test 4: Scheduled Notification');
        try {
            $scheduledTime = now()->addMinutes(1);
            $notification = $notificationService->scheduleNotification(
                $employee,
                'Reminder Check-out',
                'Jangan lupa untuk check-out sebelum pulang.',
                $scheduledTime,
                [
                    'action' => 'check_out_reminder',
                    'time' => '17:00',
                ],
                [
                    'type' => Notification::TYPE_PRESENCE,
                    'priority' => Notification::PRIORITY_NORMAL,
                ]
            );

            if ($notification) {
                $this->info("✅ Scheduled notification created with ID: {$notification->id}");
                $this->info("📅 Scheduled for: {$scheduledTime->format('Y-m-d H:i:s')}");
            } else {
                $this->warn('⚠️ Failed to create scheduled notification');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error creating scheduled notification: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 5: Get statistics
        $this->info('📊 Test 5: Notification Statistics');
        try {
            $stats = $notificationService->getStatistics();
            $this->info('📈 Notification Statistics:');
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

        // Test 6: Query notifications
        $this->info('📱 Test 6: Query Notifications');
        try {
            $unreadCount = Notification::unread()
                ->where('recipient_type', Employee::class)
                ->where('recipient_id', $employee->id)
                ->count();

            $totalCount = Notification::where('recipient_type', Employee::class)
                ->where('recipient_id', $employee->id)
                ->count();

            $this->info("📋 Employee notifications:");
            $this->info("   - Total: {$totalCount}");
            $this->info("   - Unread: {$unreadCount}");
        } catch (\Exception $e) {
            $this->error('❌ Error querying notifications: ' . $e->getMessage());
        }
        $this->newLine();

        // Test 7: Process scheduled notifications
        $this->info('📱 Test 7: Process Scheduled Notifications');
        try {
            $processedCount = $notificationService->processScheduledNotifications();
            $this->info("✅ Processed {$processedCount} scheduled notifications");
        } catch (\Exception $e) {
            $this->error('❌ Error processing scheduled notifications: ' . $e->getMessage());
        }
        $this->newLine();

        $this->info('🎉 Testing completed!');
        $this->info('💡 Check the admin panel at /admin to see the notifications');
        $this->info('📱 Use the API endpoints to test from mobile app');

        return Command::SUCCESS;
    }
}
