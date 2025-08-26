<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class TestPresenceTimezoneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:presence-timezone 
                            {--time= : Specific time to test (format: H:i)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test presence notification timezone logic';

    // Presensi schedule configuration
    protected $schedule = [
        'check_in_time' => '08:00',
        'check_out_time' => '17:00',
        'reminder_before_check_in' => 30, // minutes before check-in
        'reminder_before_check_out' => 30, // minutes before check-out
        'late_threshold' => 15, // minutes after check-in time
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Set timezone untuk Asia/Jakarta
        date_default_timezone_set('Asia/Jakarta');
        
        $specificTime = $this->option('time');
        $currentTime = $specificTime ? Carbon::createFromFormat('H:i', $specificTime) : now();

        $this->info("🕐 Presence Timezone Test");
        $this->info("========================");
        $this->info("Current Time: " . $currentTime->format('Y-m-d H:i:s'));
        $this->info("Timezone: " . date_default_timezone_get());
        $this->newLine();

        // Test semua jenis notifikasi
        $this->testCheckInTime($currentTime);
        $this->testCheckOutTime($currentTime);
        $this->testReminderTime($currentTime);
        $this->testLateTime($currentTime);

        return Command::SUCCESS;
    }

    protected function testCheckInTime($currentTime)
    {
        $this->info('📱 Testing Check-in Time (08:00)...');
        
        $checkInTime = Carbon::createFromFormat('H:i', $this->schedule['check_in_time']);
        $currentTimeOnly = $currentTime->copy()->setDate($currentTime->year, $currentTime->month, $currentTime->day);
        
        if ($currentTimeOnly->format('H:i') === $checkInTime->format('H:i')) {
            $this->info('✅ It\'s check-in time! Would send notifications.');
        } else {
            $this->info("⏰ Not check-in time yet. Check-in time: {$this->schedule['check_in_time']}");
        }
        $this->newLine();
    }

    protected function testCheckOutTime($currentTime)
    {
        $this->info('📱 Testing Check-out Time (17:00)...');
        
        $checkOutTime = Carbon::createFromFormat('H:i', $this->schedule['check_out_time']);
        $currentTimeOnly = $currentTime->copy()->setDate($currentTime->year, $currentTime->month, $currentTime->day);
        
        if ($currentTimeOnly->format('H:i') === $checkOutTime->format('H:i')) {
            $this->info('✅ It\'s check-out time! Would send notifications.');
        } else {
            $this->info("⏰ Not check-out time yet. Check-out time: {$this->schedule['check_out_time']}");
        }
        $this->newLine();
    }

    protected function testReminderTime($currentTime)
    {
        $this->info('📱 Testing Reminder Times...');
        
        $checkInTime = Carbon::createFromFormat('H:i', $this->schedule['check_in_time']);
        $checkOutTime = Carbon::createFromFormat('H:i', $this->schedule['check_out_time']);
        $currentTimeOnly = $currentTime->copy()->setDate($currentTime->year, $currentTime->month, $currentTime->day);
        
        // Check-in reminder (30 minutes before 08:00 = 07:30)
        $checkInReminderTime = $checkInTime->copy()->subMinutes($this->schedule['reminder_before_check_in']);
        if ($currentTimeOnly->format('H:i') === $checkInReminderTime->format('H:i')) {
            $this->info('✅ It\'s check-in reminder time! Would send reminders.');
        }
        
        // Check-out reminder (30 minutes before 17:00 = 16:30)
        $checkOutReminderTime = $checkOutTime->copy()->subMinutes($this->schedule['reminder_before_check_out']);
        if ($currentTimeOnly->format('H:i') === $checkOutReminderTime->format('H:i')) {
            $this->info('✅ It\'s check-out reminder time! Would send reminders.');
        }
        
        if ($currentTimeOnly->format('H:i') !== $checkInReminderTime->format('H:i') && 
            $currentTimeOnly->format('H:i') !== $checkOutReminderTime->format('H:i')) {
            $this->info("⏰ Not reminder time yet.");
            $this->info("   Check-in reminder: {$checkInReminderTime->format('H:i')}");
            $this->info("   Check-out reminder: {$checkOutReminderTime->format('H:i')}");
        }
        $this->newLine();
    }

    protected function testLateTime($currentTime)
    {
        $this->info('📱 Testing Late Time (08:15)...');
        
        $checkInTime = Carbon::createFromFormat('H:i', $this->schedule['check_in_time']);
        $lateThresholdTime = $checkInTime->copy()->addMinutes($this->schedule['late_threshold']);
        $currentTimeOnly = $currentTime->copy()->setDate($currentTime->year, $currentTime->month, $currentTime->day);
        
        if ($currentTimeOnly->format('H:i') === $lateThresholdTime->format('H:i')) {
            $this->info('✅ It\'s late notification time! Would send late notifications.');
        } else {
            $this->info("⏰ Not late notification time yet. Late threshold: {$lateThresholdTime->format('H:i')}");
        }
        $this->newLine();
    }
}
