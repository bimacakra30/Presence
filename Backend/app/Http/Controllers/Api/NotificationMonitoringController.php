<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\FirestoreService;
use App\Models\Notification;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationMonitoringController extends Controller
{
    protected $notificationService;
    protected $firestoreService;

    public function __construct(NotificationService $notificationService, FirestoreService $firestoreService)
    {
        $this->notificationService = $notificationService;
        $this->firestoreService = $firestoreService;
    }

    /**
     * Get monitoring dashboard overview
     */
    public function dashboard(Request $request): JsonResponse
    {
        $period = $request->get('period', 24); // hours
        $startTime = now()->subHours($period);
        $endTime = now();

        $stats = $this->getNotificationStats($startTime, $endTime);
        $firestoreStats = $this->getFirestoreStats();
        $performanceStats = $this->getPerformanceStats($startTime, $endTime);
        $systemStats = $this->getSystemStats();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'start_time' => $startTime->toISOString(),
                'end_time' => $endTime->toISOString(),
                'notifications' => $stats,
                'firestore' => $firestoreStats,
                'performance' => $performanceStats,
                'system' => $systemStats,
            ]
        ]);
    }

    /**
     * Get notification statistics
     */
    public function notificationStats(Request $request): JsonResponse
    {
        $period = $request->get('period', 24);
        $startTime = now()->subHours($period);
        $endTime = now();

        $stats = $this->getNotificationStats($startTime, $endTime);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get detailed breakdown by type
     */
    public function breakdownByType(Request $request): JsonResponse
    {
        $period = $request->get('period', 24);
        $startTime = now()->subHours($period);
        $endTime = now();

        $typeStats = Notification::whereBetween('created_at', [$startTime, $endTime])
            ->selectRaw('type, COUNT(*) as total, 
                        SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent,
                        SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending')
            ->groupBy('type')
            ->get()
            ->map(function($stat) {
                $successRate = $stat->total > 0 ? round(($stat->sent / $stat->total) * 100, 2) : 0;
                $failureRate = $stat->total > 0 ? round(($stat->failed / $stat->total) * 100, 2) : 0;
                
                return [
                    'type' => $stat->type,
                    'total' => $stat->total,
                    'sent' => $stat->sent,
                    'failed' => $stat->failed,
                    'pending' => $stat->pending,
                    'success_rate' => $successRate,
                    'failure_rate' => $failureRate,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $typeStats
        ]);
    }

    /**
     * Get hourly distribution
     */
    public function hourlyDistribution(Request $request): JsonResponse
    {
        $period = $request->get('period', 24);
        $startTime = now()->subHours($period);
        $endTime = now();

        $hourlyStats = Notification::whereBetween('created_at', [$startTime, $endTime])
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function($stat) {
                return [
                    'hour' => $stat->hour,
                    'hour_formatted' => sprintf('%02d:00', $stat->hour),
                    'count' => $stat->count
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $hourlyStats
        ]);
    }

    /**
     * Get top recipients
     */
    public function topRecipients(Request $request): JsonResponse
    {
        $period = $request->get('period', 24);
        $limit = $request->get('limit', 10);
        $startTime = now()->subHours($period);
        $endTime = now();

        $recipientStats = Notification::whereBetween('created_at', [$startTime, $endTime])
            ->selectRaw('recipient_id, COUNT(*) as count')
            ->groupBy('recipient_id')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(function($stat) {
                $employee = Employee::find($stat->recipient_id);
                return [
                    'recipient_id' => $stat->recipient_id,
                    'employee_name' => $employee ? $employee->name : 'Unknown',
                    'employee_email' => $employee ? $employee->email : 'Unknown',
                    'count' => $stat->count
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $recipientStats
        ]);
    }

    /**
     * Get recent notifications
     */
    public function recentNotifications(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);
        $status = $request->get('status');

        $query = Notification::with('recipient')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($status) {
            $query->where('status', $status);
        }

        $notifications = $query->get()
            ->map(function($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'type' => $notification->type,
                    'priority' => $notification->priority,
                    'status' => $notification->status,
                    'recipient_name' => $notification->recipient ? $notification->recipient->name : 'Unknown',
                    'created_at' => $notification->created_at->toISOString(),
                    'sent_at' => $notification->sent_at ? $notification->sent_at->toISOString() : null,
                    'read_at' => $notification->read_at ? $notification->read_at->toISOString() : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    /**
     * Get FCM token statistics
     */
    public function fcmTokenStats(): JsonResponse
    {
        try {
            $allTokens = $this->firestoreService->getAllActiveFcmTokens();
            
            $stats = [
                'total_tokens' => count($allTokens),
                'unique_employees' => count(array_unique(array_column($allTokens, 'employee_uid'))),
                'platforms' => [],
                'recent_tokens' => 0,
                'old_tokens' => 0,
            ];

            // Count platforms and recent tokens
            foreach ($allTokens as $token) {
                $platform = $token['platform'] ?? 'unknown';
                $stats['platforms'][$platform] = ($stats['platforms'][$platform] ?? 0) + 1;
                
                $createdAt = $token['created_at'] ? Carbon::parse($token['created_at']) : null;
                if ($createdAt && $createdAt->gt(now()->subDays(7))) {
                    $stats['recent_tokens']++;
                } else {
                    $stats['old_tokens']++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get FCM token stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system performance stats
     */
    public function systemStats(): JsonResponse
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        // Get database connection status
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'memory' => [
                    'usage' => $this->formatBytes($memoryUsage),
                    'peak' => $this->formatBytes($memoryPeak),
                    'limit' => $memoryLimit,
                ],
                'queue' => [
                    'pending_jobs' => $pendingJobs,
                    'failed_jobs' => $failedJobs,
                ],
                'database' => [
                    'status' => $dbStatus,
                ],
                'timestamp' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Get alerts and recommendations
     */
    public function alerts(Request $request): JsonResponse
    {
        $period = $request->get('period', 24);
        $startTime = now()->subHours($period);
        $endTime = now();

        $stats = $this->getNotificationStats($startTime, $endTime);
        $firestoreStats = $this->getFirestoreStats();
        $systemStats = $this->getSystemStats();

        $alerts = [];

        // Success rate alerts
        if ($stats['success_rate'] < 80) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Low success rate: {$stats['success_rate']}%",
                'recommendation' => 'Check FCM token validity and Firebase configuration'
            ];
        }

        if ($stats['failure_rate'] > 20) {
            $alerts[] = [
                'type' => 'error',
                'message' => "High failure rate: {$stats['failure_rate']}%",
                'recommendation' => 'Review error logs and FCM setup'
            ];
        }

        // Token alerts
        if ($firestoreStats['total_tokens'] == 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'No FCM tokens found',
                'recommendation' => 'Ensure mobile apps are properly configured and sending tokens'
            ];
        }

        if ($firestoreStats['recent_tokens'] < $firestoreStats['total_tokens'] * 0.5) {
            $alerts[] = [
                'type' => 'info',
                'message' => 'Many tokens are old',
                'recommendation' => 'Consider implementing token refresh mechanism'
            ];
        }

        // System alerts
        if ($systemStats['failed_jobs'] > 10) {
            $alerts[] = [
                'type' => 'error',
                'message' => "High number of failed jobs: {$systemStats['failed_jobs']}",
                'recommendation' => 'Check queue worker and job processing'
            ];
        }

        if (empty($alerts)) {
            $alerts[] = [
                'type' => 'success',
                'message' => 'System is performing well',
                'recommendation' => 'No immediate action needed'
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $alerts
        ]);
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

    protected function getFirestoreStats()
    {
        try {
            $allTokens = $this->firestoreService->getAllActiveFcmTokens();
            
            $stats = [
                'total_tokens' => count($allTokens),
                'unique_employees' => count(array_unique(array_column($allTokens, 'employee_uid'))),
                'platforms' => [],
                'recent_tokens' => 0,
            ];

            foreach ($allTokens as $token) {
                $platform = $token['platform'] ?? 'unknown';
                $stats['platforms'][$platform] = ($stats['platforms'][$platform] ?? 0) + 1;
                
                $createdAt = $token['created_at'] ? Carbon::parse($token['created_at']) : null;
                if ($createdAt && $createdAt->gt(now()->subDays(7))) {
                    $stats['recent_tokens']++;
                }
            }

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
        $deliveryTimes = Notification::whereBetween('created_at', [$startTime, $endTime])
            ->where('status', Notification::STATUS_SENT)
            ->whereNotNull('sent_at')
            ->get()
            ->map(function($notification) {
                return $notification->created_at->diffInSeconds($notification->sent_at);
            });

        $avgDeliveryTime = $deliveryTimes->count() > 0 ? $deliveryTimes->avg() : 0;

        return [
            'avg_delivery_time' => round($avgDeliveryTime, 2),
            'min_delivery_time' => $deliveryTimes->count() > 0 ? $deliveryTimes->min() : 0,
            'max_delivery_time' => $deliveryTimes->count() > 0 ? $deliveryTimes->max() : 0,
        ];
    }

    protected function getSystemStats()
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
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
}
