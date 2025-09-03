<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RealTimeEmployeeSyncService;
use App\Services\FirestoreChangeListenerService;
use Illuminate\Support\Facades\Log;

class RealTimeEmployeeSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employee:sync-realtime 
                            {--force : Force immediate sync bypassing time check}
                            {--interval= : Set custom sync interval in seconds}
                            {--status : Show sync status only}
                            {--start-listener : Start Firestore change listener}
                            {--stop-listener : Stop Firestore change listener}
                            {--listener-status : Show listener status}
                            {--simulate-change : Simulate Firestore change for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Real-time employee sync from Firestore to MySQL';

    /**
     * Execute the console command.
     */
    public function handle(RealTimeEmployeeSyncService $syncService, FirestoreChangeListenerService $listenerService)
    {
        try {
            // Show status only
            if ($this->option('status')) {
                $this->showSyncStatus($syncService);
                return 0;
            }

            // Listener management
            if ($this->option('start-listener')) {
                $this->startListener($listenerService);
                return 0;
            }

            if ($this->option('stop-listener')) {
                $this->stopListener($listenerService);
                return 0;
            }

            if ($this->option('listener-status')) {
                $this->showListenerStatus($listenerService);
                return 0;
            }

            if ($this->option('simulate-change')) {
                $this->simulateChange($listenerService);
                return 0;
            }

            // Set custom interval if provided
            if ($interval = $this->option('interval')) {
                $syncService->setSyncInterval((int) $interval);
                $this->info("Sync interval set to {$interval} seconds");
            }

            // Force sync if requested
            if ($this->option('force')) {
                $this->info('🔄 Force syncing employees...');
                $result = $syncService->forceSync();
            } else {
                $this->info('🔄 Starting real-time employee sync...');
                $result = $syncService->startRealTimeSync();
            }

            // Display results
            $this->displaySyncResults($result);

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            Log::error('Real-time sync command failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 1;
        }
    }

    /**
     * Display sync results in a formatted way
     */
    protected function displaySyncResults($result)
    {
        $this->newLine();

        switch ($result['status']) {
            case 'success':
                $this->info('✅ Sync completed successfully!');
                $this->newLine();
                
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Total Employees', $result['results']['total']],
                        ['Updated', $result['results']['updated']],
                        ['No Changes', $result['results']['no_changes']],
                        ['Errors', $result['results']['errors']],
                        ['Timestamp', $result['timestamp']]
                    ]
                );
                break;

            case 'skipped':
                $this->warn('⏭️  Sync skipped: ' . $result['reason']);
                break;

            case 'no_data':
                $this->warn('⚠️  No data to sync: ' . $result['message']);
                break;

            case 'error':
                $this->error('❌ Sync error: ' . $result['error']);
                break;

            default:
                $this->info('ℹ️  Sync result: ' . json_encode($result, JSON_PRETTY_PRINT));
        }

        // Show next sync info
        $this->newLine();
        $this->showSyncStatus($this->laravel->make(RealTimeEmployeeSyncService::class));
    }

    /**
     * Show current sync status
     */
    protected function showSyncStatus(RealTimeEmployeeSyncService $syncService)
    {
        $status = $syncService->getSyncStatus();

        $this->info('📊 Real-Time Sync Status:');
        $this->table(
            ['Property', 'Value'],
            [
                ['Last Sync', $status['last_sync']],
                ['Next Sync In', $status['next_sync_in']],
                ['Sync Interval', $status['sync_interval']],
                ['Sync Due', $status['is_sync_due'] ? 'Yes' : 'No']
            ]
        );
    }

    /**
     * Start Firestore change listener
     */
    protected function startListener(FirestoreChangeListenerService $listenerService)
    {
        $this->info('🎧 Starting Firestore change listener...');
        
        $result = $listenerService->startListening();
        
        if ($result['status'] === 'started') {
            $this->info('✅ Listener started successfully!');
            $this->info('📡 Now listening for Firestore changes...');
        } else {
            $this->warn('⚠️  Listener status: ' . $result['status']);
            if (isset($result['message'])) {
                $this->info('💬 ' . $result['message']);
            }
        }
    }

    /**
     * Stop Firestore change listener
     */
    protected function stopListener(FirestoreChangeListenerService $listenerService)
    {
        $this->info('🛑 Stopping Firestore change listener...');
        
        $result = $listenerService->stopListening();
        
        if ($result['status'] === 'stopped') {
            $this->info('✅ Listener stopped successfully!');
        } else {
            $this->warn('⚠️  Listener status: ' . $result['status']);
            if (isset($result['message'])) {
                $this->info('💬 ' . $result['message']);
            }
        }
    }

    /**
     * Show listener status
     */
    protected function showListenerStatus(FirestoreChangeListenerService $listenerService)
    {
        $status = $listenerService->getListenerStatus();
        $health = $listenerService->healthCheck();

        $this->info('🎧 Firestore Change Listener Status:');
        $this->table(
            ['Property', 'Value'],
            [
                ['Is Listening', $status['is_listening'] ? 'Yes' : 'No'],
                ['Cache Status', $status['cache_status'] ? 'Active' : 'Inactive'],
                ['Last Activity', $status['last_activity'] ?? 'None'],
                ['Health Status', $health['status']],
                ['Message', $health['message']]
            ]
        );

        if (isset($health['recommendation'])) {
            $this->newLine();
            $this->warn('💡 Recommendation: ' . $health['recommendation']);
        }
    }

    /**
     * Simulate Firestore change for testing
     */
    protected function simulateChange(FirestoreChangeListenerService $listenerService)
    {
        $this->info('🧪 Simulating Firestore change for testing...');
        
        // Get employee for testing
        $employee = \App\Models\Employee::first();
        
        if (!$employee) {
            $this->error('❌ No employees found for testing');
            return;
        }

        $this->info("👤 Testing with employee: {$employee->name} (UID: {$employee->uid})");
        
        // Simulate UPDATE change
        $result = $listenerService->simulateChange('UPDATE', $employee->uid, [
            'name' => $employee->name,
            'email' => $employee->email,
            'status' => 'aktif'
        ]);

        if ($result['status'] === 'simulated') {
            $this->info('✅ Change simulation successful!');
            $this->info('📊 Sync result: ' . $result['sync_result']['status']);
        } else {
            $this->error('❌ Change simulation failed: ' . $result['status']);
        }
    }
}
