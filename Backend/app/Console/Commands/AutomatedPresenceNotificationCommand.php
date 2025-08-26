<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Services\FirestoreService;
use App\Models\Employee;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutomatedPresenceNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:automated-presence 
                            {--type=check-in : Type of notification (check-in, check-out, reminder)}
                            {--time= : Specific time to check (format: H:i, default: current time)}
                            {--test : Test mode without sending actual notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated presence notifications based on schedule';

    protected $notificationService;
    protected $firestoreService;

    // Presensi schedule configuration
    protected $schedule = [
        'check_in_time' => '08:00',
        'check_out_time' => '17:00',
        'reminder_before_check_in' => 30, // minutes before check-in
        'reminder_before_check_out' => 30, // minutes before check-out
        'late_threshold' => 15, // minutes after check-in time
    ];

    public function __construct(NotificationService $notificationService, FirestoreService $firestoreService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
        $this->firestoreService = $firestoreService;
        
        // Set timezone untuk Asia/Jakarta
        date_default_timezone_set('Asia/Jakarta');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $specificTime = $this->option('time');
        $testMode = $this->option('test');

        $currentTime = $specificTime ? Carbon::createFromFormat('H:i', $specificTime) : now();
        
        $this->info("🤖 Automated Presence Notification System");
        $this->info("=====================================");
        $this->info("Type: {$type}");
        $this->info("Time: " . $currentTime->format('Y-m-d H:i:s'));
        $this->info("Mode: " . ($testMode ? 'TEST' : 'PRODUCTION'));
        $this->newLine();

        switch ($type) {
            case 'check-in':
                $this->handleCheckInNotifications($currentTime, $testMode);
                break;
            case 'check-out':
                $this->handleCheckOutNotifications($currentTime, $testMode);
                break;
            case 'reminder':
                $this->handleReminderNotifications($currentTime, $testMode);
                break;
            case 'late':
                $this->handleLateNotifications($currentTime, $testMode);
                break;
            default:
                $this->error("❌ Invalid notification type: {$type}");
                return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Handle check-in notifications
     */
    protected function handleCheckInNotifications($currentTime, $testMode)
    {
        $this->info('📱 Processing Check-in Notifications...');
        
        // Check if it's time for check-in (08:00)
        $checkInTime = Carbon::createFromFormat('H:i', $this->schedule['check_in_time']);
        $currentTimeOnly = $currentTime->copy()->setDate($currentTime->year, $currentTime->month, $currentTime->day);
        
        if ($currentTimeOnly->format('H:i') === $checkInTime->format('H:i')) {
            $this->sendCheckInNotifications($testMode);
        } else {
            $this->info("⏰ Not check-in time yet. Check-in time: {$this->schedule['check_in_time']}");
        }
    }

    /**
     * Handle check-out notifications
     */
    protected function handleCheckOutNotifications($currentTime, $testMode)
    {
        $this->info('📱 Processing Check-out Notifications...');
        
        // Check if it's time for check-out (17:00)
        $checkOutTime = Carbon::createFromFormat('H:i', $this->schedule['check_out_time']);
        $currentTimeOnly = $currentTime->copy()->setDate($currentTime->year, $currentTime->month, $currentTime->day);
        
        if ($currentTimeOnly->format('H:i') === $checkOutTime->format('H:i')) {
            $this->sendCheckOutNotifications($testMode);
        } else {
            $this->info("⏰ Not check-out time yet. Check-out time: {$this->schedule['check_out_time']}");
        }
    }

    /**
     * Handle reminder notifications
     */
    protected function handleReminderNotifications($currentTime, $testMode)
    {
        $this->info('📱 Processing Reminder Notifications...');
        
        $checkInTime = Carbon::createFromFormat('H:i', $this->schedule['check_in_time']);
        $checkOutTime = Carbon::createFromFormat('H:i', $this->schedule['check_out_time']);
        $currentTimeOnly = $currentTime->copy()->setDate($currentTime->year, $currentTime->month, $currentTime->day);
        
        // Check-in reminder (30 minutes before 08:00 = 07:30)
        $checkInReminderTime = $checkInTime->copy()->subMinutes($this->schedule['reminder_before_check_in']);
        if ($currentTimeOnly->format('H:i') === $checkInReminderTime->format('H:i')) {
            $this->sendCheckInReminders($testMode);
        }
        
        // Check-out reminder (30 minutes before 17:00 = 16:30)
        $checkOutReminderTime = $checkOutTime->copy()->subMinutes($this->schedule['reminder_before_check_out']);
        if ($currentTimeOnly->format('H:i') === $checkOutReminderTime->format('H:i')) {
            $this->sendCheckOutReminders($testMode);
        }
        
        if ($currentTimeOnly->format('H:i') !== $checkInReminderTime->format('H:i') && 
            $currentTimeOnly->format('H:i') !== $checkOutReminderTime->format('H:i')) {
            $this->info("⏰ Not reminder time yet.");
            $this->info("   Check-in reminder: {$checkInReminderTime->format('H:i')}");
            $this->info("   Check-out reminder: {$checkOutReminderTime->format('H:i')}");
        }
    }

    /**
     * Handle late notifications
     */
    protected function handleLateNotifications($currentTime, $testMode)
    {
        $this->info('📱 Processing Late Notifications...');
        
        $checkInTime = Carbon::createFromFormat('H:i', $this->schedule['check_in_time']);
        $lateThresholdTime = $checkInTime->copy()->addMinutes($this->schedule['late_threshold']);
        $currentTimeOnly = $currentTime->copy()->setDate($currentTime->year, $currentTime->month, $currentTime->day);
        
        if ($currentTimeOnly->format('H:i') === $lateThresholdTime->format('H:i')) {
            $this->sendLateNotifications($testMode);
        } else {
            $this->info("⏰ Not late notification time yet. Late threshold: {$lateThresholdTime->format('H:i')}");
        }
    }

    /**
     * Send check-in notifications
     */
    protected function sendCheckInNotifications($testMode)
    {
        $this->info('✅ Sending Check-in Notifications...');
        
        $employees = $this->getActiveEmployees();
        $sentCount = 0;
        
        foreach ($employees as $employee) {
            $message = $this->getCheckInMessage($employee);
            
            if ($testMode) {
                $this->info("   [TEST] Would send to {$employee->name}: {$message['title']}");
                $sentCount++;
            } else {
                $result = $this->notificationService->sendToEmployeeWithFirestoreTokens(
                    $employee->uid,
                    $message['title'],
                    $message['body'],
                    [
                        'action' => 'check_in',
                        'time' => $this->schedule['check_in_time'],
                        'date' => now()->format('Y-m-d'),
                        'type' => 'automated_check_in',
                    ],
                    [
                        'type' => Notification::TYPE_PRESENCE,
                        'priority' => Notification::PRIORITY_NORMAL,
                    ]
                );
                
                if ($result) {
                    $this->info("   ✅ Sent to {$employee->name}");
                    $sentCount++;
                } else {
                    $this->warn("   ⚠️ Failed to send to {$employee->name}");
                }
            }
        }
        
        $this->info("📊 Total sent: {$sentCount} notifications");
        Log::info("Automated check-in notifications sent: {$sentCount} notifications");
    }

    /**
     * Send check-out notifications
     */
    protected function sendCheckOutNotifications($testMode)
    {
        $this->info('✅ Sending Check-out Notifications...');
        
        $employees = $this->getActiveEmployees();
        $sentCount = 0;
        
        foreach ($employees as $employee) {
            $message = $this->getCheckOutMessage($employee);
            
            if ($testMode) {
                $this->info("   [TEST] Would send to {$employee->name}: {$message['title']}");
                $sentCount++;
            } else {
                $result = $this->notificationService->sendToEmployeeWithFirestoreTokens(
                    $employee->uid,
                    $message['title'],
                    $message['body'],
                    [
                        'action' => 'check_out',
                        'time' => $this->schedule['check_out_time'],
                        'date' => now()->format('Y-m-d'),
                        'type' => 'automated_check_out',
                    ],
                    [
                        'type' => Notification::TYPE_PRESENCE,
                        'priority' => Notification::PRIORITY_NORMAL,
                    ]
                );
                
                if ($result) {
                    $this->info("   ✅ Sent to {$employee->name}");
                    $sentCount++;
                } else {
                    $this->warn("   ⚠️ Failed to send to {$employee->name}");
                }
            }
        }
        
        $this->info("📊 Total sent: {$sentCount} notifications");
        Log::info("Automated check-out notifications sent: {$sentCount} notifications");
    }

    /**
     * Send check-in reminders
     */
    protected function sendCheckInReminders($testMode)
    {
        $this->info('⏰ Sending Check-in Reminders...');
        
        $employees = $this->getActiveEmployees();
        $sentCount = 0;
        
        foreach ($employees as $employee) {
            $message = $this->getCheckInReminderMessage($employee);
            
            if ($testMode) {
                $this->info("   [TEST] Would send to {$employee->name}: {$message['title']}");
                $sentCount++;
            } else {
                $result = $this->notificationService->sendToEmployeeWithFirestoreTokens(
                    $employee->uid,
                    $message['title'],
                    $message['body'],
                    [
                        'action' => 'check_in_reminder',
                        'time' => $this->schedule['check_in_time'],
                        'date' => now()->format('Y-m-d'),
                        'type' => 'automated_reminder',
                    ],
                    [
                        'type' => Notification::TYPE_PRESENCE,
                        'priority' => Notification::PRIORITY_NORMAL,
                    ]
                );
                
                if ($result) {
                    $this->info("   ✅ Sent to {$employee->name}");
                    $sentCount++;
                } else {
                    $this->warn("   ⚠️ Failed to send to {$employee->name}");
                }
            }
        }
        
        $this->info("📊 Total sent: {$sentCount} reminders");
        Log::info("Automated check-in reminders sent: {$sentCount} notifications");
    }

    /**
     * Send check-out reminders
     */
    protected function sendCheckOutReminders($testMode)
    {
        $this->info('⏰ Sending Check-out Reminders...');
        
        $employees = $this->getActiveEmployees();
        $sentCount = 0;
        
        foreach ($employees as $employee) {
            $message = $this->getCheckOutReminderMessage($employee);
            
            if ($testMode) {
                $this->info("   [TEST] Would send to {$employee->name}: {$message['title']}");
                $sentCount++;
            } else {
                $result = $this->notificationService->sendToEmployeeWithFirestoreTokens(
                    $employee->uid,
                    $message['title'],
                    $message['body'],
                    [
                        'action' => 'check_out_reminder',
                        'time' => $this->schedule['check_out_time'],
                        'date' => now()->format('Y-m-d'),
                        'type' => 'automated_reminder',
                    ],
                    [
                        'type' => Notification::TYPE_PRESENCE,
                        'priority' => Notification::PRIORITY_NORMAL,
                    ]
                );
                
                if ($result) {
                    $this->info("   ✅ Sent to {$employee->name}");
                    $sentCount++;
                } else {
                    $this->warn("   ⚠️ Failed to send to {$employee->name}");
                }
            }
        }
        
        $this->info("📊 Total sent: {$sentCount} reminders");
        Log::info("Automated check-out reminders sent: {$sentCount} notifications");
    }

    /**
     * Send late notifications
     */
    protected function sendLateNotifications($testMode)
    {
        $this->info('⚠️ Sending Late Notifications...');
        
        $employees = $this->getActiveEmployees();
        $sentCount = 0;
        
        foreach ($employees as $employee) {
            $message = $this->getLateMessage($employee);
            
            if ($testMode) {
                $this->info("   [TEST] Would send to {$employee->name}: {$message['title']}");
                $sentCount++;
            } else {
                $result = $this->notificationService->sendToEmployeeWithFirestoreTokens(
                    $employee->uid,
                    $message['title'],
                    $message['body'],
                    [
                        'action' => 'late_notification',
                        'check_in_time' => $this->schedule['check_in_time'],
                        'late_threshold' => $this->schedule['late_threshold'],
                        'date' => now()->format('Y-m-d'),
                        'type' => 'automated_late',
                    ],
                    [
                        'type' => Notification::TYPE_PRESENCE,
                        'priority' => Notification::PRIORITY_HIGH,
                    ]
                );
                
                if ($result) {
                    $this->info("   ✅ Sent to {$employee->name}");
                    $sentCount++;
                } else {
                    $this->warn("   ⚠️ Failed to send to {$employee->name}");
                }
            }
        }
        
        $this->info("📊 Total sent: {$sentCount} late notifications");
        Log::info("Automated late notifications sent: {$sentCount} notifications");
    }

    /**
     * Get active employees
     */
    protected function getActiveEmployees()
    {
        return Employee::where('status', 'aktif')
            ->orWhere('status', 'active')
            ->get();
    }

    /**
     * Get check-in message
     */
    protected function getCheckInMessage($employee)
    {
        return [
            'title' => 'Waktu Check-in',
            'body' => "Selamat pagi {$employee->name}! Sekarang adalah waktu check-in. Silakan lakukan check-in untuk mencatat kehadiran Anda hari ini."
        ];
    }

    /**
     * Get check-out message
     */
    protected function getCheckOutMessage($employee)
    {
        return [
            'title' => 'Waktu Check-out',
            'body' => "Hai {$employee->name}! Sekarang adalah waktu check-out. Jangan lupa untuk melakukan check-out sebelum pulang."
        ];
    }

    /**
     * Get check-in reminder message
     */
    protected function getCheckInReminderMessage($employee)
    {
        return [
            'title' => 'Reminder Check-in',
            'body' => "Hai {$employee->name}! Dalam {$this->schedule['reminder_before_check_in']} menit lagi adalah waktu check-in. Siapkan diri Anda untuk check-in tepat waktu."
        ];
    }

    /**
     * Get check-out reminder message
     */
    protected function getCheckOutReminderMessage($employee)
    {
        return [
            'title' => 'Reminder Check-out',
            'body' => "Hai {$employee->name}! Dalam {$this->schedule['reminder_before_check_out']} menit lagi adalah waktu check-out. Selesaikan pekerjaan Anda dan siap untuk check-out."
        ];
    }

    /**
     * Get late message
     */
    protected function getLateMessage($employee)
    {
        return [
            'title' => 'Keterlambatan Check-in',
            'body' => "Hai {$employee->name}! Anda belum melakukan check-in. Waktu check-in adalah {$this->schedule['check_in_time']}. Silakan check-in segera untuk menghindari keterlambatan."
        ];
    }
}
