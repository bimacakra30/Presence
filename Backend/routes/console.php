<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automated presence notifications scheduling
Schedule::command('notifications:automated-presence --type=reminder')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:automated-presence --type=check-in')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:automated-presence --type=late')
    ->dailyAt('08:15')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:automated-presence --type=reminder')
    ->dailyAt('16:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:automated-presence --type=check-out')
    ->dailyAt('17:00')
    ->withoutOverlapping()
    ->runInBackground();

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
