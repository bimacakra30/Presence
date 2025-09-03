<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ActivePermitSyncService;
use App\Services\FirestoreService;

class ActivePermitSyncCommand extends Command
{
    protected $signature = 'permit:sync-active 
                            {--start : Start continuous sync}
                            {--stop : Stop continuous sync}
                            {--status : Show sync status}
                            {--force : Force immediate sync}
                            {--health : Check sync health}';

    protected $description = 'Manage active permit sync service';

    protected $activePermitSyncService;

    public function __construct(ActivePermitSyncService $activePermitSyncService)
    {
        parent::__construct();
        $this->activePermitSyncService = $activePermitSyncService;
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
        $this->info('🚀 Starting active permit sync service...');
        
        try {
            $result = $this->activePermitSyncService->startContinuousSync();
            
            if ($result['status'] === 'started') {
                $this->info('✅ Active permit sync service started successfully!');
                $this->info('📡 Now continuously monitoring permit changes...');
                $this->info('⏰ Sync interval: 30 seconds');
                $this->info('💡 Use --status to check current status');
            } else {
                $this->warn('⚠️  ' . $result['status']);
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to start permit sync service: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    protected function stopSync()
    {
        $this->info('🛑 Stopping active permit sync service...');
        
        try {
            $result = $this->activePermitSyncService->stopContinuousSync();
            
            if ($result['status'] === 'stopped') {
                $this->info('✅ Active permit sync service stopped successfully!');
            } else {
                $this->warn('⚠️  ' . $result['status']);
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to stop permit sync service: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    protected function showStatus()
    {
        $this->info('📊 Active Permit Sync Service Status');
        $this->info('==================================');
        
        try {
            $status = $this->activePermitSyncService->getSyncStatus();
            
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
                $this->info('🟢 Permit sync service is running and monitoring changes');
            } else {
                $this->info('🔴 Permit sync service is not running');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to get permit sync status: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    protected function forceSync()
    {
        $this->info('⚡ Forcing immediate permit sync...');
        
        try {
            $result = $this->activePermitSyncService->forceSync();
            
            if ($result['status'] === 'success') {
                $this->info('✅ Force permit sync completed successfully!');
                $this->info('📝 ' . $result['message']);
            } else {
                $this->error('❌ Force permit sync failed: ' . $result['message']);
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to force permit sync: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    protected function checkHealth()
    {
        $this->info('🏥 Active Permit Sync Service Health Check');
        $this->info('=========================================');
        
        try {
            $health = $this->activePermitSyncService->healthCheck();
            
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
                $this->info('🎉 Permit sync service is healthy and working properly!');
            } elseif ($health['status'] === 'warning') {
                $this->warn('⚠️  Permit sync service has some issues: ' . $health['message']);
            } else {
                $this->error('❌ Permit sync service is not healthy: ' . $health['message']);
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to check permit sync health: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}

