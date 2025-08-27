<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automated presence notifications scheduling - REMOVED (handled locally)
// All presence reminders and notifications are now handled in local app

// Cleanup old FCM tokens weekly
Schedule::command('fcm:cleanup-tokens')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->withoutOverlapping();

// Monitor FCM notifications daily
Schedule::command('fcm:monitor --period=24 --export')
    ->daily()
    ->at('23:59')
    ->withoutOverlapping();
