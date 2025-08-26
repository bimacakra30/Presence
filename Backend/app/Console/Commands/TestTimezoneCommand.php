<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class TestTimezoneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:timezone';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test timezone configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🕐 Timezone Test');
        $this->info('==============');
        
        $this->info('System Time: ' . date('Y-m-d H:i:s'));
        $this->info('PHP Timezone: ' . date_default_timezone_get());
        $this->info('Laravel Timezone: ' . config('app.timezone'));
        
        // Set timezone untuk Carbon
        Carbon::setTimezone(config('app.timezone'));
        
        $this->info('Laravel Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
        
        $this->newLine();
        $this->info('📅 Test Schedule Times:');
        $this->info('Check-in Reminder (07:30): ' . Carbon::createFromFormat('H:i', '07:30')->format('Y-m-d H:i:s'));
        $this->info('Check-in Time (08:00): ' . Carbon::createFromFormat('H:i', '08:00')->format('Y-m-d H:i:s'));
        $this->info('Late Time (08:15): ' . Carbon::createFromFormat('H:i', '08:15')->format('Y-m-d H:i:s'));
        
        $this->newLine();
        $this->info('🔍 Timezone Analysis:');
        $currentHour = Carbon::now()->format('H:i');
        $this->info("Current time: {$currentHour}");
        
        if ($currentHour === '07:30') {
            $this->info('✅ It\'s check-in reminder time!');
        } elseif ($currentHour === '08:00') {
            $this->info('✅ It\'s check-in time!');
        } elseif ($currentHour === '08:15') {
            $this->info('✅ It\'s late notification time!');
        } else {
            $this->info('⏰ Not notification time yet');
        }
        
        return Command::SUCCESS;
    }
}
