<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Services\FirestoreService;
use App\Models\Notification;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MonitorFcmNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:monitor 
                            {--period=24 : Period in hours to analyze (default: 24)}
                            {--detailed : Show detailed breakdown}
                            {--export : Export to CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor FCM notification performance and statistics';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService, FirestoreService $firestoreService)
    {
        $period = $this->option('period');
        $detailed = $this->option('detailed');
        $export = $this->option('export');

        $this->info("📊 FCM Notification Monitoring Dashboard");
        $this->info("=====================================");
        $this->info("Period: Last {$period} hours");
        $this->newLine();

        $startTime = now()->subHours($period);
        $endTime = now();

        // Get statistics
        $stats = $this->getNotificationStats($startTime, $endTime);
        $firestoreStats = $this->getFirestoreStats($firestoreService);
        $performanceStats = $this->getPerformanceStats($startTime, $endTime);

        // Display overview
        $this->displayOverview($stats, $firestoreStats, $performanceStats);

        if ($detailed) {
            $this->displayDetailedBreakdown($startTime, $endTime);
        }

        if ($export) {
            $this->exportToCSV($startTime, $endTime);
        }

        // Show recommendations
        $this->displayRecommendations($stats, $firestoreStats, $performanceStats);

        return Command::SUCCESS;
    }

    protected function getNotificationStats($startTime, $endTime)
    {
        $stats = [
            'total' => Notification::whereBetween('created_at', [$startTime, $endTime])->count(),
            'sent' => Notification::whereBetween('created_at', [$startTime, $endTime])
                ->where('status', Notification::STATUS_SENT)->count(),
            'failed' => Notification::whereBetween('created_at', [$startTime, $endTime])
                ->where('status', Notification::STATUS_FAILED)->count(),
            'pending' => Notification::whereBetween('created_at', [$startTime, $endTime])
                ->where('status', Notification::STATUS_PENDING)->count(),
            'scheduled' => Notification::whereBetween('created_at', [$startTime, $endTime])
                ->where('status', Notification::STATUS_SCHEDULED)->count(),
        ];

        $stats['success_rate'] = $stats['total'] > 0 ? round(($stats['sent'] / $stats['total']) * 100, 2) : 0;
        $stats['failure_rate'] = $stats['total'] > 0 ? round(($stats['failed'] / $stats['total']) * 100, 2) : 0;

        return $stats;
    }

    protected function getFirestoreStats($firestoreService)
    {
        try {
            $allTokens = $firestoreService->getAllActiveFcmTokens();
            
            $stats = [
                'total_tokens' => count($allTokens),
                'unique_employees' => count(array_unique(array_column($allTokens, 'employee_uid'))),
                'platforms' => [],
                'recent_tokens' => 0,
            ];

            // Count platforms
            foreach ($allTokens as $token) {
                $platform = $token['platform'] ?? 'unknown';
                $stats['platforms'][$platform] = ($stats['platforms'][$platform] ?? 0) + 1;
            }

            // Count recent tokens (last 7 days)
            $recentTokens = array_filter($allTokens, function($token) {
                $createdAt = $token['created_at'] ? Carbon::parse($token['created_at']) : null;
                return $createdAt && $createdAt->gt(now()->subDays(7));
            });
            $stats['recent_tokens'] = count($recentTokens);

            return $stats;
        } catch (\Exception $e) {
            return [
                'total_tokens' => 0,
                'unique_employees' => 0,
                'platforms' => [],
                'recent_tokens' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function getPerformanceStats($startTime, $endTime)
    {
        // Get average delivery time
        $deliveryTimes = Notification::whereBetween('created_at', [$startTime, $endTime])
            ->where('status', Notification::STATUS_SENT)
            ->whereNotNull('sent_at')
            ->get()
            ->map(function($notification) {
                return $notification->created_at->diffInSeconds($notification->sent_at);
            });

        $avgDeliveryTime = $deliveryTimes->count() > 0 ? $deliveryTimes->avg() : 0;

        // Get hourly distribution
        $hourlyStats = Notification::whereBetween('created_at', [$startTime, $endTime])
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        return [
            'avg_delivery_time' => round($avgDeliveryTime, 2),
            'hourly_distribution' => $hourlyStats,
            'peak_hour' => array_keys($hourlyStats, max($hourlyStats))[0] ?? null,
        ];
    }

    protected function displayOverview($stats, $firestoreStats, $performanceStats)
    {
        $this->info('📈 OVERVIEW STATISTICS');
        $this->info('=====================');

        // Notification Statistics
        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Notifications', $stats['total'], '100%'],
                ['Successfully Sent', $stats['sent'], $stats['success_rate'] . '%'],
                ['Failed', $stats['failed'], $stats['failure_rate'] . '%'],
                ['Pending', $stats['pending'], '-'],
                ['Scheduled', $stats['scheduled'], '-'],
            ]
        );

        // Firestore Statistics
        $this->newLine();
        $this->info('🔥 FIRESTORE STATISTICS');
        $this->info('======================');

        if (isset($firestoreStats['error'])) {
            $this->error('Error getting Firestore stats: ' . $firestoreStats['error']);
        } else {
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total FCM Tokens', $firestoreStats['total_tokens']],
                    ['Unique Employees', $firestoreStats['unique_employees']],
                    ['Recent Tokens (7 days)', $firestoreStats['recent_tokens']],
                ]
            );

            if (!empty($firestoreStats['platforms'])) {
                $this->newLine();
                $this->info('📱 Platform Distribution:');
                foreach ($firestoreStats['platforms'] as $platform => $count) {
                    $this->line("   {$platform}: {$count} tokens");
                }
            }
        }

        // Performance Statistics
        $this->newLine();
        $this->info('⚡ PERFORMANCE STATISTICS');
        $this->info('========================');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Average Delivery Time', $performanceStats['avg_delivery_time'] . ' seconds'],
                ['Peak Hour', $performanceStats['peak_hour'] ? $performanceStats['peak_hour'] . ':00' : 'N/A'],
            ]
        );
    }

    protected function displayDetailedBreakdown($startTime, $endTime)
    {
        $this->newLine();
        $this->info('📋 DETAILED BREAKDOWN');
        $this->info('====================');

        // By Type
        $this->info('By Notification Type:');
        $typeStats = Notification::whereBetween('created_at', [$startTime, $endTime])
            ->selectRaw('type, COUNT(*) as count, 
                        SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent,
                        SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            ->groupBy('type')
            ->get();

        $typeTable = [];
        foreach ($typeStats as $stat) {
            $successRate = $stat->count > 0 ? round(($stat->sent / $stat->count) * 100, 2) : 0;
            $typeTable[] = [
                $stat->type,
                $stat->count,
                $stat->sent,
                $stat->failed,
                $successRate . '%'
            ];
        }

        $this->table(
            ['Type', 'Total', 'Sent', 'Failed', 'Success Rate'],
            $typeTable
        );

        // By Priority
        $this->newLine();
        $this->info('By Priority:');
        $priorityStats = Notification::whereBetween('created_at', [$startTime, $endTime])
            ->selectRaw('priority, COUNT(*) as count, 
                        SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent,
                        SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            ->groupBy('priority')
            ->get();

        $priorityTable = [];
        foreach ($priorityStats as $stat) {
            $successRate = $stat->count > 0 ? round(($stat->sent / $stat->count) * 100, 2) : 0;
            $priorityTable[] = [
                $stat->priority,
                $stat->count,
                $stat->sent,
                $stat->failed,
                $successRate . '%'
            ];
        }

        $this->table(
            ['Priority', 'Total', 'Sent', 'Failed', 'Success Rate'],
            $priorityTable
        );

        // Hourly Distribution
        $this->newLine();
        $this->info('Hourly Distribution:');
        $hourlyStats = Notification::whereBetween('created_at', [$startTime, $endTime])
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $hourlyTable = [];
        foreach ($hourlyStats as $stat) {
            $hourlyTable[] = [
                $stat->hour . ':00',
                $stat->count
            ];
        }

        $this->table(
            ['Hour', 'Notifications'],
            $hourlyTable
        );

        // Top Recipients
        $this->newLine();
        $this->info('Top Recipients:');
        $recipientStats = Notification::whereBetween('created_at', [$startTime, $endTime])
            ->selectRaw('recipient_id, COUNT(*) as count')
            ->groupBy('recipient_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $recipientTable = [];
        foreach ($recipientStats as $stat) {
            $employee = Employee::find($stat->recipient_id);
            $name = $employee ? $employee->name : 'Unknown';
            $recipientTable[] = [
                $name,
                $stat->count
            ];
        }

        $this->table(
            ['Employee', 'Notifications Received'],
            $recipientTable
        );
    }

    protected function exportToCSV($startTime, $endTime)
    {
        $filename = 'fcm_monitoring_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $path = storage_path('app/exports/' . $filename);

        // Create directory if not exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $notifications = Notification::whereBetween('created_at', [$startTime, $endTime])
            ->with('recipient')
            ->get();

        $file = fopen($path, 'w');

        // Write headers
        fputcsv($file, [
            'ID', 'Title', 'Body', 'Type', 'Priority', 'Status', 
            'Recipient', 'Created At', 'Sent At', 'Read At'
        ]);

        // Write data
        foreach ($notifications as $notification) {
            $recipientName = $notification->recipient ? $notification->recipient->name : 'Unknown';
            
            fputcsv($file, [
                $notification->id,
                $notification->title,
                $notification->body,
                $notification->type,
                $notification->priority,
                $notification->status,
                $recipientName,
                $notification->created_at,
                $notification->sent_at,
                $notification->read_at,
            ]);
        }

        fclose($file);

        $this->info("📄 Export saved to: {$path}");
    }

    protected function displayRecommendations($stats, $firestoreStats, $performanceStats)
    {
        $this->newLine();
        $this->info('💡 RECOMMENDATIONS');
        $this->info('==================');

        $recommendations = [];

        // Success rate recommendations
        if ($stats['success_rate'] < 80) {
            $recommendations[] = "⚠️ Success rate is low ({$stats['success_rate']}%). Check FCM token validity and Firebase configuration.";
        }

        if ($stats['failure_rate'] > 20) {
            $recommendations[] = "❌ High failure rate ({$stats['failure_rate']}%). Review error logs and FCM setup.";
        }

        // Token recommendations
        if ($firestoreStats['total_tokens'] == 0) {
            $recommendations[] = "🔑 No FCM tokens found. Ensure mobile apps are properly configured and sending tokens.";
        }

        if ($firestoreStats['recent_tokens'] < $firestoreStats['total_tokens'] * 0.5) {
            $recommendations[] = "🔄 Many tokens are old. Consider implementing token refresh mechanism.";
        }

        // Performance recommendations
        if ($performanceStats['avg_delivery_time'] > 10) {
            $recommendations[] = "⏱️ Slow delivery time ({$performanceStats['avg_delivery_time']}s). Consider optimizing notification processing.";
        }

        if (empty($recommendations)) {
            $recommendations[] = "✅ System is performing well! No immediate action needed.";
        }

        foreach ($recommendations as $index => $recommendation) {
            $this->line(($index + 1) . ". " . $recommendation);
        }
    }
}
