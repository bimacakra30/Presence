<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;

class ProcessScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled notifications and send them';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService)
    {
        $this->info('Processing scheduled notifications...');

        try {
            $processedCount = $notificationService->processScheduledNotifications();

            if ($processedCount > 0) {
                $this->info("Successfully processed {$processedCount} scheduled notifications.");
            } else {
                $this->info('No scheduled notifications to process.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error processing scheduled notifications: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
