<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Process scheduled notifications every minute
        $schedule->command('notifications:process-scheduled')->everyMinute();
        
        // Automated presence notifications
        // Check-in reminder (07:30 - 30 minutes before check-in)
        $schedule->command('notifications:automated-presence --type=reminder')
            ->dailyAt('07:30')
            ->withoutOverlapping()
            ->runInBackground();
        
        // Check-in notification (08:00)
        $schedule->command('notifications:automated-presence --type=check-in')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->runInBackground();
        
        // Late notification (08:15 - 15 minutes after check-in)
        $schedule->command('notifications:automated-presence --type=late')
            ->dailyAt('08:15')
            ->withoutOverlapping()
            ->runInBackground();
        
        // Check-out reminder (16:30 - 30 minutes before check-out)
        $schedule->command('notifications:automated-presence --type=reminder')
            ->dailyAt('16:30')
            ->withoutOverlapping()
            ->runInBackground();
        
        // Check-out notification (17:00)
        $schedule->command('notifications:automated-presence --type=check-out')
            ->dailyAt('17:00')
            ->withoutOverlapping()
            ->runInBackground();
        
        // Cleanup old FCM tokens weekly
        $schedule->command('fcm:cleanup-tokens')
            ->weekly()
            ->sundays()
            ->at('02:00')
            ->withoutOverlapping();
        
        // Monitor FCM notifications daily
        $schedule->command('fcm:monitor --period=24 --export')
            ->daily()
            ->at('23:59')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
