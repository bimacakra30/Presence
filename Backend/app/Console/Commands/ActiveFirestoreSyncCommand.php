<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ActiveFirestoreSyncService;
use App\Services\FirestoreService;

class ActiveFirestoreSyncCommand extends Command
{
    protected $signature = 'firestore:sync-active 
                            {--start : Start continuous sync}
                            {--stop : Stop continuous sync}
                            {--status : Show sync status}
                            {--force : Force immediate sync}
                            {--health : Check sync health}';

    protected $description = 'Manage active Firestore sync service';

    protected $activeSyncService;

    public function __construct(ActiveFirestoreSyncService $activeSyncService)
    {
        parent::__construct();
        $this->activeSyncService = $activeSyncService;
    }

    public function handle()
    {
        if ($this->option('start')) {
            return $this->startSync();
        }

        if ($this->option('stop')) {
            return $this->stopSync();
        }

        if ($this->option('status')) {
            return $this->showStatus();
        }

        if ($this->option('force')) {
            return $this->forceSync();
        }

        if ($this->option('health')) {
            return $this->checkHealth();
        }

        // Default: show status
        return $this->showStatus();
    }

    protected function startSync()
    {
        $this->info('🚀 Starting active Firestore sync service...');
        
        try {
            $result = $this->activeSyncService->startContinuousSync();
            
            if ($result['status'] === 'started') {
                $this->info('✅ Active sync service started successfully!');
                $this->info('📡 Now continuously monitoring Firestore changes...');
                $this->info('⏰ Sync interval: 30 seconds');
                $this->info('💡 Use --status to check current status');
            } else {
                $this->warn('⚠️  ' . $result['status']);
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to start sync service: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    protected function stopSync()
    {
        $this->info('🛑 Stopping active Firestore sync service...');
        
        try {
            $result = $this->activeSyncService->stopContinuousSync();
            
            if ($result['status'] === 'stopped') {
                $this->info('✅ Active sync service stopped successfully!');
            } else {
                $this->warn('⚠️  ' . $result['status']);
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to stop sync service: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    protected function showStatus()
    {
        $this->info('📊 Active Firestore Sync Service Status');
        $this->info('=====================================');
        
        try {
            $status = $this->activeSyncService->getSyncStatus();
            
            $this->table(
                ['Property', 'Value'],
                [
                    ['Is Running', $status['is_running'] ? 'Yes' : 'No'],
                    ['Cache Status', $status['cache_status'] ? 'Active' : 'Inactive'],
                    ['Last Sync Time', $status['last_sync_time'] ?? 'Never'],
                    ['Cache Last Sync', $status['cache_last_sync'] ?? 'N/A'],
                    ['Sync Interval', $status['sync_interval']],
                ]
            );
            
            if ($status['is_running']) {
                $this->info('🟢 Service is running and monitoring Firestore changes');
            } else {
                $this->info('🔴 Service is not running');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to get status: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    protected function forceSync()
    {
        $this->info('⚡ Forcing immediate sync...');
        
        try {
            $result = $this->activeSyncService->forceSync();
            
            if ($result['status'] === 'success') {
                $this->info('✅ Force sync completed successfully!');
                $this->info('📝 ' . $result['message']);
            } else {
                $this->error('❌ Force sync failed: ' . $result['message']);
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to force sync: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    protected function checkHealth()
    {
        $this->info('🏥 Active Firestore Sync Service Health Check');
        $this->info('============================================');
        
        try {
            $health = $this->activeSyncService->healthCheck();
            
            $statusIcon = match($health['status']) {
                'healthy' => '🟢',
                'warning' => '🟡',
                'stopped' => '🔴',
                default => '❓'
            };
            
            $this->table(
                ['Property', 'Value'],
                [
                    ['Status', $statusIcon . ' ' . ucfirst($health['status'])],
                    ['Message', $health['message']],
                ]
            );
            
            if ($health['status'] === 'healthy') {
                $this->info('🎉 Service is healthy and working properly!');
            } elseif ($health['status'] === 'warning') {
                $this->warn('⚠️  Service has some issues: ' . $health['message']);
            } else {
                $this->error('❌ Service is not healthy: ' . $health['message']);
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to check health: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}

