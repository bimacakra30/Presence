<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RealTimeEmployeeSyncService;
use App\Services\FirestoreChangeListenerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RealTimeSyncController extends Controller
{
    protected $syncService;
    protected $listenerService;

    public function __construct(
        RealTimeEmployeeSyncService $syncService,
        FirestoreChangeListenerService $listenerService
    ) {
        $this->syncService = $syncService;
        $this->listenerService = $listenerService;
    }

    /**
     * Get current sync status
     */
    public function getStatus(): JsonResponse
    {
        try {
            $status = $this->syncService->getSyncStatus();
            
            return response()->json([
                'success' => true,
                'data' => $status,
                'message' => 'Sync status retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get sync status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Trigger manual sync
     */
    public function triggerSync(Request $request): JsonResponse
    {
        try {
            $force = $request->boolean('force', false);
            $interval = $request->input('interval');

            // Set custom interval if provided
            if ($interval) {
                $this->syncService->setSyncInterval((int) $interval);
            }

            // Start sync
            if ($force) {
                $result = $this->syncService->forceSync();
            } else {
                $result = $this->syncService->startRealTimeSync();
            }

            Log::info('Manual sync triggered', [
                'force' => $force,
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Sync triggered successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Manual sync failed', [
                'error' => $e->getMessage(),
                'force' => $request->boolean('force', false)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set sync interval
     */
    public function setInterval(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'interval' => 'required|integer|min:60|max:3600'
            ]);

            $interval = (int) $request->input('interval');
            $this->syncService->setSyncInterval($interval);

            Log::info('Sync interval updated', ['new_interval' => $interval]);

            return response()->json([
                'success' => true,
                'message' => "Sync interval updated to {$interval} seconds",
                'data' => [
                    'interval' => $interval
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to set sync interval', [
                'error' => $e->getMessage(),
                'interval' => $request->input('interval')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to set interval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $status = $this->syncService->getSyncStatus();
            $listenerStatus = $this->listenerService->getListenerStatus();
            $listenerHealth = $this->listenerService->healthCheck();
            
            // Get additional stats from cache
            $stats = [
                'sync_status' => $status,
                'listener_status' => $listenerStatus,
                'listener_health' => $listenerHealth,
                'cache_info' => [
                    'last_sync_cache' => cache('last_employee_sync'),
                    'firestore_cache_keys' => [
                        'employees_list' => cache('firestore_employees_list') ? 'exists' : 'not_exists',
                        'employees_minimal' => cache('firestore_employees_minimal') ? 'exists' : 'not_exists'
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Sync statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get sync statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start Firestore change listener
     */
    public function startListener(): JsonResponse
    {
        try {
            $result = $this->listenerService->startListening();
            
            Log::info('Listener started via API', $result);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Firestore change listener started successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start listener', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start listener: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stop Firestore change listener
     */
    public function stopListener(): JsonResponse
    {
        try {
            $result = $this->listenerService->stopListening();
            
            Log::info('Listener stopped via API', $result);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Firestore change listener stopped successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to stop listener', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to stop listener: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get listener status
     */
    public function getListenerStatus(): JsonResponse
    {
        try {
            $status = $this->listenerService->getListenerStatus();
            $health = $this->listenerService->healthCheck();

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $status,
                    'health' => $health
                ],
                'message' => 'Listener status retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get listener status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get listener status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simulate Firestore change for testing
     */
    public function simulateChange(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'change_type' => 'required|in:CREATE,UPDATE,DELETE',
                'document_id' => 'required|string',
                'document_data' => 'nullable|array'
            ]);

            $result = $this->listenerService->simulateChange(
                $request->input('change_type'),
                $request->input('document_id'),
                $request->input('document_data')
            );

            Log::info('Change simulation triggered via API', $result);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Change simulation completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to simulate change', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to simulate change: ' . $e->getMessage()
            ], 500);
        }
    }
}
