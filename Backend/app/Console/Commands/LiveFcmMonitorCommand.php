<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Notification;
use App\Services\FirestoreService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LiveFcmMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:live-monitor 
                            {--interval=5 : Update interval in seconds (default: 5)}
                            {--duration=300 : Monitor duration in seconds (default: 300)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Real-time monitoring of FCM notifications';

    protected $startTime;
    protected $lastCheckTime;

    /**
     * Execute the console command.
     */
    public function handle(FirestoreService $firestoreService)
    {
        $interval = $this->option('interval');
        $duration = $this->option('duration');

        $this->startTime = now();
        $this->lastCheckTime = $this->startTime;

        $this->info("🔴 Live FCM Notification Monitor");
        $this->info("==============================");
        $this->info("Update interval: {$interval} seconds");
        $this->info("Duration: {$duration} seconds");
        $this->info("Started at: " . $this->startTime->format('Y-m-d H:i:s'));
        $this->newLine();

        $endTime = $this->startTime->addSeconds($duration);

        while (now()->lt($endTime)) {
            $this->clearScreen();
            $this->displayLiveStats($firestoreService);
            
            // Wait for next update
            sleep($interval);
        }

        $this->newLine();
        $this->info("✅ Monitoring completed at: " . now()->format('Y-m-d H:i:s'));
        
        return Command::SUCCESS;
    }

    protected function clearScreen()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            system('cls');
        } else {
            system('clear');
        }
    }

    protected function displayLiveStats($firestoreService)
    {
        $currentTime = now();
        $elapsed = $this->startTime->diffInSeconds($currentTime);
        $remaining = $this->lastCheckTime->diffInSeconds($currentTime);

        $this->info("🕐 Live Monitor - " . $currentTime->format('Y-m-d H:i:s'));
        $this->info("⏱️ Elapsed: {$elapsed}s | Since last update: {$remaining}s");
        $this->newLine();

        // Get recent notifications
        $recentNotifications = $this->getRecentNotifications();
        $firestoreStats = $this->getLiveFirestoreStats($firestoreService);
        $systemStats = $this->getSystemStats();

        // Display recent activity
        $this->displayRecentActivity($recentNotifications);
        
        // Display current stats
        $this->displayCurrentStats($firestoreStats, $systemStats);
        
        // Display alerts
        $this->displayAlerts($recentNotifications, $firestoreStats);
    }

    protected function getRecentNotifications()
    {
        $recentNotifications = Notification::where('created_at', '>=', $this->lastCheckTime)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $this->lastCheckTime = now();

        return $recentNotifications;
    }

    protected function getLiveFirestoreStats($firestoreService)
    {
        try {
            $allTokens = $firestoreService->getAllActiveFcmTokens();
            
            return [
                'total_tokens' => count($allTokens),
                'unique_employees' => count(array_unique(array_column($allTokens, 'employee_uid'))),
                'platforms' => $this->countPlatforms($allTokens),
            ];
        } catch (\Exception $e) {
            return [
                'total_tokens' => 0,
                'unique_employees' => 0,
                'platforms' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    protected function countPlatforms($tokens)
    {
        $platforms = [];
        foreach ($tokens as $token) {
            $platform = $token['platform'] ?? 'unknown';
            $platforms[$platform] = ($platforms[$platform] ?? 0) + 1;
        }
        return $platforms;
    }

    protected function getSystemStats()
    {
        // Get system performance stats
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        // Get queue stats
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        return [
            'memory_usage' => $this->formatBytes($memoryUsage),
            'memory_peak' => $this->formatBytes($memoryPeak),
            'memory_limit' => $memoryLimit,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
        ];
    }

    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    protected function displayRecentActivity($notifications)
    {
        $this->info('📱 RECENT ACTIVITY');
        $this->info('==================');

        if ($notifications->isEmpty()) {
            $this->line('   No new notifications since last update');
        } else {
            foreach ($notifications as $notification) {
                $statusIcon = $this->getStatusIcon($notification->status);
                $time = $notification->created_at->format('H:i:s');
                $title = substr($notification->title, 0, 30);
                
                $this->line("   {$time} {$statusIcon} {$title} ({$notification->type})");
            }
        }
        
        $this->newLine();
    }

    protected function displayCurrentStats($firestoreStats, $systemStats)
    {
        // Firestore Stats
        $this->info('🔥 FIRESTORE STATUS');
        $this->info('==================');
        
        if (isset($firestoreStats['error'])) {
            $this->error('   Error: ' . $firestoreStats['error']);
        } else {
            $this->line("   Total FCM Tokens: {$firestoreStats['total_tokens']}");
            $this->line("   Unique Employees: {$firestoreStats['unique_employees']}");
            
            if (!empty($firestoreStats['platforms'])) {
                $this->line("   Platforms:");
                foreach ($firestoreStats['platforms'] as $platform => $count) {
                    $this->line("     {$platform}: {$count}");
                }
            }
        }

        $this->newLine();

        // System Stats
        $this->info('⚙️ SYSTEM STATUS');
        $this->info('===============');
        $this->line("   Memory Usage: {$systemStats['memory_usage']}");
        $this->line("   Memory Peak: {$systemStats['memory_peak']}");
        $this->line("   Memory Limit: {$systemStats['memory_limit']}");
        $this->line("   Pending Jobs: {$systemStats['pending_jobs']}");
        $this->line("   Failed Jobs: {$systemStats['failed_jobs']}");
        
        $this->newLine();
    }

    protected function displayAlerts($notifications, $firestoreStats)
    {
        $alerts = [];

        // Check for failed notifications
        $failedCount = $notifications->where('status', 'failed')->count();
        if ($failedCount > 0) {
            $alerts[] = "❌ {$failedCount} notifications failed";
        }

        // Check for high failure rate
        $totalCount = $notifications->count();
        if ($totalCount > 0) {
            $failureRate = ($failedCount / $totalCount) * 100;
            if ($failureRate > 50) {
                $alerts[] = "⚠️ High failure rate: " . round($failureRate, 1) . "%";
            }
        }

        // Check for no FCM tokens
        if ($firestoreStats['total_tokens'] == 0) {
            $alerts[] = "🔑 No FCM tokens available";
        }

        // Check for system issues
        if ($systemStats['failed_jobs'] > 10) {
            $alerts[] = "🚨 High number of failed jobs: {$systemStats['failed_jobs']}";
        }

        if (!empty($alerts)) {
            $this->info('🚨 ALERTS');
            $this->info('=========');
            foreach ($alerts as $alert) {
                $this->error("   {$alert}");
            }
            $this->newLine();
        }
    }

    protected function getStatusIcon($status)
    {
        switch ($status) {
            case 'sent':
                return '✅';
            case 'failed':
                return '❌';
            case 'pending':
                return '⏳';
            case 'scheduled':
                return '📅';
            default:
                return '❓';
        }
    }
}
