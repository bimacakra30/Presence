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
        
        // Automated presence notifications - REMOVED (handled locally)
        // All presence reminders and notifications are now handled in local app
        
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
