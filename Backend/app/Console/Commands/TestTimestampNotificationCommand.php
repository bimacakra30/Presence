<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Models\Employee;
use Carbon\Carbon;

class TestTimestampNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test-timestamp 
                            {--time=07:30 : Time to test (HH:MM format)}
                            {--type=reminder : Notification type (reminder, check-in, late, checkout-reminder, checkout)}
                            {--employee-id= : Specific employee ID to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test automated notifications with specific timestamp without changing server time';

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
        $testTime = $this->option('time');
        $type = $this->option('type');
        $employeeId = $this->option('employee-id');

        $this->info("🧪 Timestamp Test Notification System");
        $this->info("=====================================");
        $this->info("Test Time: {$testTime}");
        $this->info("Type: {$type}");
        $this->info("Current Server Time: " . now()->format('H:i:s'));

        // Validate time format
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $testTime)) {
            $this->error("❌ Invalid time format. Use HH:MM format (e.g., 07:30)");
            return Command::FAILURE;
        }

        // Get employees to notify
        $employees = [];
        if ($employeeId) {
            $employee = Employee::find($employeeId);
            if (!$employee) {
                $this->error("❌ Employee with ID {$employeeId} not found!");
                return Command::FAILURE;
            }
            $employees = [$employee];
        } else {
            $employees = Employee::where('status', 'aktif')->get();
        }

        if ($employees->isEmpty()) {
            $this->error("❌ No active employees found!");
            return Command::FAILURE;
        }

        $this->info("📱 Found " . $employees->count() . " employee(s) to notify");

        // Process notifications based on type and time
        $this->processNotifications($employees, $type, $testTime);

        return Command::SUCCESS;
    }

    protected function processNotifications($employees, $type, $testTime)
    {
        $this->info("\n📱 Processing {$type} notifications for {$testTime}...");

        switch ($type) {
            case 'reminder':
                $this->sendReminderNotifications($employees, $testTime);
                break;
            case 'check-in':
                $this->sendCheckInNotifications($employees, $testTime);
                break;
            case 'late':
                $this->sendLateNotifications($employees, $testTime);
                break;
            case 'checkout-reminder':
                $this->sendCheckoutReminderNotifications($employees, $testTime);
                break;
            case 'checkout':
                $this->sendCheckoutNotifications($employees, $testTime);
                break;
            default:
                $this->error("❌ Invalid notification type: {$type}");
                return;
        }
    }

    protected function sendReminderNotifications($employees, $testTime)
    {
        $this->info("⏰ Sending Check-in Reminders...");
        $sentCount = 0;

        foreach ($employees as $employee) {
            try {
                $result = $this->notificationService->sendToRecipient(
                    $employee,
                    'Reminder Check-in',
                    "Waktunya untuk check-in! Silakan lakukan presensi masuk.",
                    [
                        'action' => 'check_in_reminder',
                        'timestamp' => $testTime,
                        'employee_id' => $employee->id,
                        'test_mode' => true,
                    ]
                );

                if ($result) {
                    $this->info("   ✅ Sent to {$employee->name}");
                    $sentCount++;
                } else {
                    $this->info("   ⚠️ Failed to send to {$employee->name}");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Error sending to {$employee->name}: " . $e->getMessage());
            }
        }

        $this->info("📊 Total sent: {$sentCount} reminders");
    }

    protected function sendCheckInNotifications($employees, $testTime)
    {
        $this->info("✅ Sending Check-in Notifications...");
        $sentCount = 0;

        foreach ($employees as $employee) {
            try {
                $result = $this->notificationService->sendToRecipient(
                    $employee,
                    'Waktu Check-in',
                    "Sekarang adalah waktu check-in. Silakan lakukan presensi masuk.",
                    [
                        'action' => 'check_in_time',
                        'timestamp' => $testTime,
                        'employee_id' => $employee->id,
                        'test_mode' => true,
                    ]
                );

                if ($result) {
                    $this->info("   ✅ Sent to {$employee->name}");
                    $sentCount++;
                } else {
                    $this->info("   ⚠️ Failed to send to {$employee->name}");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Error sending to {$employee->name}: " . $e->getMessage());
            }
        }

        $this->info("📊 Total sent: {$sentCount} notifications");
    }

    protected function sendLateNotifications($employees, $testTime)
    {
        $this->info("⚠️ Sending Late Notifications...");
        $sentCount = 0;

        foreach ($employees as $employee) {
            try {
                $result = $this->notificationService->sendToRecipient(
                    $employee,
                    'Terlambat Check-in',
                    "Anda terlambat melakukan check-in. Silakan lakukan presensi masuk segera.",
                    [
                        'action' => 'late_check_in',
                        'timestamp' => $testTime,
                        'employee_id' => $employee->id,
                        'test_mode' => true,
                    ]
                );

                if ($result) {
                    $this->info("   ✅ Sent to {$employee->name}");
                    $sentCount++;
                } else {
                    $this->info("   ⚠️ Failed to send to {$employee->name}");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Error sending to {$employee->name}: " . $e->getMessage());
            }
        }

        $this->info("📊 Total sent: {$sentCount} notifications");
    }

    protected function sendCheckoutReminderNotifications($employees, $testTime)
    {
        $this->info("⏰ Sending Check-out Reminders...");
        $sentCount = 0;

        foreach ($employees as $employee) {
            try {
                $result = $this->notificationService->sendToRecipient(
                    $employee,
                    'Reminder Check-out',
                    "Waktunya untuk check-out! Silakan lakukan presensi keluar.",
                    [
                        'action' => 'check_out_reminder',
                        'timestamp' => $testTime,
                        'employee_id' => $employee->id,
                        'test_mode' => true,
                    ]
                );

                if ($result) {
                    $this->info("   ✅ Sent to {$employee->name}");
                    $sentCount++;
                } else {
                    $this->info("   ⚠️ Failed to send to {$employee->name}");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Error sending to {$employee->name}: " . $e->getMessage());
            }
        }

        $this->info("📊 Total sent: {$sentCount} reminders");
    }

    protected function sendCheckoutNotifications($employees, $testTime)
    {
        $this->info("✅ Sending Check-out Notifications...");
        $sentCount = 0;

        foreach ($employees as $employee) {
            try {
                $result = $this->notificationService->sendToRecipient(
                    $employee,
                    'Waktu Check-out',
                    "Sekarang adalah waktu check-out. Silakan lakukan presensi keluar.",
                    [
                        'action' => 'check_out_time',
                        'timestamp' => $testTime,
                        'employee_id' => $employee->id,
                        'test_mode' => true,
                    ]
                );

                if ($result) {
                    $this->info("   ✅ Sent to {$employee->name}");
                    $sentCount++;
                } else {
                    $this->info("   ⚠️ Failed to send to {$employee->name}");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Error sending to {$employee->name}: " . $e->getMessage());
            }
        }

        $this->info("📊 Total sent: {$sentCount} notifications");
    }
}
