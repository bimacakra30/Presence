<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Services\FirestoreService;
use Carbon\Carbon;

class CleanupFcmTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:cleanup-tokens 
                            {--days=30 : Number of days old to consider for cleanup (default: 30)}
                            {--dry-run : Show what would be cleaned without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old FCM tokens from Firestore';

    protected $notificationService;
    protected $firestoreService;

    public function __construct(NotificationService $notificationService, FirestoreService $firestoreService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
        $this->firestoreService = $firestoreService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysOld = $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("🧹 FCM Token Cleanup");
        $this->info("===================");
        $this->info("Days old: {$daysOld}");
        $this->info("Mode: " . ($dryRun ? 'DRY RUN' : 'ACTUAL CLEANUP'));
        $this->newLine();

        try {
            $cleanedCount = $this->notificationService->cleanupOldFcmTokensFromFirestore($daysOld);

            if ($dryRun) {
                $this->info("📊 Would clean up {$cleanedCount} old FCM tokens");
                $this->info("💡 Run without --dry-run to actually perform cleanup");
            } else {
                $this->info("✅ Successfully cleaned up {$cleanedCount} old FCM tokens");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error during cleanup: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
