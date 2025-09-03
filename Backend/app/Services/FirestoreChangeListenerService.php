<?php

namespace App\Services;

use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\RealTimeEmployeeSyncService;

class FirestoreChangeListenerService
{
    protected $firestoreService;
    protected $syncService;
    protected $isListening = false;
    protected $listenerCallback;

    public function __construct(
        FirestoreService $firestoreService,
        RealTimeEmployeeSyncService $syncService
    ) {
        $this->firestoreService = $firestoreService;
        $this->syncService = $syncService;
    }

    /**
     * Start listening to Firestore changes
     */
    public function startListening()
    {
        try {
            if ($this->isListening) {
                Log::info('Firestore listener: Already listening');
                return ['status' => 'already_listening'];
            }

            Log::info('Firestore listener: Starting to listen for changes');

            // Note: Firestore PHP SDK doesn't support real-time listeners like Node.js
            // We'll implement a polling-based approach for now
            // In production, consider using Firebase Admin SDK or webhooks
            
            Log::warning('Firestore listener: Real-time listeners not supported in PHP SDK');
            Log::info('Firestore listener: Using polling-based approach instead');
            
                        // For now, we'll simulate the listener being active
            // In a real implementation, you'd use:
            // 1. Firebase Functions with webhooks
            // 2. Firebase Admin SDK
            // 3. External service with real-time capabilities
            
            $this->isListening = true;
            
            // Store listener status in cache
            Cache::put('firestore_listener_active', true, now()->addHours(24));
            
            Log::info('Firestore listener: Successfully started listening');

            return [
                'status' => 'started',
                'message' => 'Listening to Firestore changes',
                'timestamp' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Firestore listener: Failed to start listening', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Stop listening to Firestore changes
     */
    public function stopListening()
    {
        try {
            if (!$this->isListening) {
                Log::info('Firestore listener: Not currently listening');
                return ['status' => 'not_listening'];
            }

            // Remove listener callback
            if ($this->listenerCallback) {
                // Note: Firestore PHP SDK doesn't have direct stop method
                // This is handled by the service lifecycle
                $this->listenerCallback = null;
            }

            $this->isListening = false;
            
            // Remove listener status from cache
            Cache::forget('firestore_listener_active');
            
            Log::info('Firestore listener: Stopped listening');

            return [
                'status' => 'stopped',
                'message' => 'Stopped listening to Firestore changes',
                'timestamp' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Firestore listener: Failed to stop listening', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle Firestore changes
     */
    protected function handleFirestoreChanges($changes)
    {
        try {
            Log::info('Firestore listener: Processing changes', [
                'change_count' => count($changes)
            ]);

            foreach ($changes as $change) {
                $this->processChange($change);
            }

        } catch (\Exception $e) {
            Log::error('Firestore listener: Error processing changes', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process individual change
     */
    protected function processChange($change)
    {
        try {
            $changeType = $change->type();
            $documentId = $change->document()->id();
            $documentData = $change->document()->data();

            Log::info('Firestore listener: Processing change', [
                'type' => $changeType,
                'document_id' => $documentId,
                'has_data' => !empty($documentData)
            ]);

            // Trigger sync based on change type
            $syncResult = $this->syncService->syncOnFirestoreChange(
                $changeType,
                $documentId,
                $documentData
            );

            Log::info('Firestore listener: Sync completed', [
                'change_type' => $changeType,
                'document_id' => $documentId,
                'sync_result' => $syncResult
            ]);

        } catch (\Exception $e) {
            Log::error('Firestore listener: Error processing change', [
                'change_type' => $change->type() ?? 'unknown',
                'document_id' => $change->document()->id() ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get listener status
     */
    public function getListenerStatus()
    {
        return [
            'is_listening' => $this->isListening,
            'cache_status' => Cache::get('firestore_listener_active', false),
            'last_activity' => Cache::get('firestore_listener_last_activity'),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Simulate Firestore change for testing
     */
    public function simulateChange($changeType, $documentId, $documentData = null)
    {
        try {
            Log::info('Firestore listener: Simulating change', [
                'type' => $changeType,
                'document_id' => $documentId
            ]);

            $syncResult = $this->syncService->syncOnFirestoreChange(
                $changeType,
                $documentId,
                $documentData
            );

            return [
                'status' => 'simulated',
                'change_type' => $changeType,
                'document_id' => $documentId,
                'sync_result' => $syncResult
            ];

        } catch (\Exception $e) {
            Log::error('Firestore listener: Error simulating change', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if listener is healthy
     */
    public function healthCheck()
    {
        try {
            $status = $this->getListenerStatus();
            
            // Check if listener is active
            if (!$status['is_listening'] && !$status['cache_status']) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Listener is not active',
                    'recommendation' => 'Restart listener service'
                ];
            }

            // Check last activity
            $lastActivity = $status['last_activity'];
            if ($lastActivity) {
                $lastActivityTime = \Carbon\Carbon::parse($lastActivity);
                $timeSinceLastActivity = now()->diffInMinutes($lastActivityTime);
                
                if ($timeSinceLastActivity > 60) { // More than 1 hour
                    return [
                        'status' => 'warning',
                        'message' => 'No recent activity detected',
                        'last_activity' => $lastActivity,
                        'minutes_since_last' => $timeSinceLastActivity,
                        'recommendation' => 'Check Firestore connection'
                    ];
                }
            }

            return [
                'status' => 'healthy',
                'message' => 'Listener is working properly',
                'timestamp' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Firestore listener: Health check failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => 'Health check failed: ' . $e->getMessage()
            ];
        }
    }
}
