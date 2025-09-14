<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\RealTimeEmployeeSyncService;
use App\Services\FirestoreService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FirestoreWebhookController extends Controller
{
    protected $syncService;
    protected $firestoreService;

    public function __construct()
    {
        $this->firestoreService = new FirestoreService();
        $this->syncService = new RealTimeEmployeeSyncService($this->firestoreService);
    }

    /**
     * Handle Firestore webhook for employee changes
     */
    public function handleEmployeeChange(Request $request): JsonResponse
    {
        try {
            Log::info('Firestore webhook: Employee change received', [
                'headers' => $request->headers->all(),
                'body' => $request->all()
            ]);

            // Validate webhook signature (optional but recommended)
            if (!$this->validateWebhookSignature($request)) {
                Log::warning('Firestore webhook: Invalid signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Parse webhook data
            $webhookData = $this->parseWebhookData($request);
            
            if (!$webhookData) {
                Log::warning('Firestore webhook: Invalid data format');
                return response()->json(['error' => 'Invalid data format'], 400);
            }

            // Process the change
            $result = $this->processEmployeeChange($webhookData);

            Log::info('Firestore webhook: Employee change processed', [
                'result' => $result
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Employee change processed successfully',
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Firestore webhook: Error processing employee change', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Firestore webhook for permit changes
     */
    public function handlePermitChange(Request $request): JsonResponse
    {
        try {
            Log::info('Firestore webhook: Permit change received', [
                'headers' => $request->headers->all(),
                'body' => $request->all()
            ]);

            // Validate webhook signature
            if (!$this->validateWebhookSignature($request)) {
                Log::warning('Firestore webhook: Invalid signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Parse webhook data
            $webhookData = $this->parseWebhookData($request);
            
            if (!$webhookData) {
                Log::warning('Firestore webhook: Invalid data format');
                return response()->json(['error' => 'Invalid data format'], 400);
            }

            // Process the change
            $result = $this->processPermitChange($webhookData);

            Log::info('Firestore webhook: Permit change processed', [
                'result' => $result
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Permit change processed successfully',
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Firestore webhook: Error processing permit change', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Firestore webhook for presence changes
     */
    public function handlePresenceChange(Request $request): JsonResponse
    {
        try {
            Log::info('Firestore webhook: Presence change received', [
                'headers' => $request->headers->all(),
                'body' => $request->all()
            ]);

            // Validate webhook signature
            if (!$this->validateWebhookSignature($request)) {
                Log::warning('Firestore webhook: Invalid signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Parse webhook data
            $webhookData = $this->parseWebhookData($request);
            
            if (!$webhookData) {
                Log::warning('Firestore webhook: Invalid data format');
                return response()->json(['error' => 'Invalid data format'], 400);
            }

            // Process the change
            $result = $this->processPresenceChange($webhookData);

            Log::info('Firestore webhook: Presence change processed', [
                'result' => $result
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Presence change processed successfully',
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Firestore webhook: Error processing presence change', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate webhook signature for security
     */
    protected function validateWebhookSignature(Request $request): bool
    {
        // Get webhook secret from config
        $webhookSecret = config('firebase.webhook_secret');
        
        if (!$webhookSecret) {
            Log::warning('Firestore webhook: No webhook secret configured');
            return true; // Allow if no secret configured (development)
        }

        $signature = $request->header('X-Webhook-Signature');
        $payload = $request->getContent();
        
        if (!$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Parse webhook data from request
     */
    protected function parseWebhookData(Request $request): ?array
    {
        $data = $request->all();
        
        // Expected format from Firebase Functions
        if (isset($data['eventType']) && isset($data['documentId'])) {
            return [
                'event_type' => $data['eventType'], // CREATE, UPDATE, DELETE
                'document_id' => $data['documentId'],
                'document_data' => $data['documentData'] ?? null,
                'old_document_data' => $data['oldDocumentData'] ?? null,
                'collection' => $data['collection'] ?? 'employees',
                'timestamp' => $data['timestamp'] ?? now()->toISOString()
            ];
        }

        // Alternative format
        if (isset($data['change_type']) && isset($data['document_id'])) {
            return [
                'event_type' => $data['change_type'],
                'document_id' => $data['document_id'],
                'document_data' => $data['document_data'] ?? null,
                'old_document_data' => $data['old_document_data'] ?? null,
                'collection' => $data['collection'] ?? 'employees',
                'timestamp' => $data['timestamp'] ?? now()->toISOString()
            ];
        }

        return null;
    }

    /**
     * Process employee change
     */
    protected function processEmployeeChange(array $webhookData): array
    {
        $eventType = $webhookData['event_type'];
        $documentId = $webhookData['document_id'];
        $documentData = $webhookData['document_data'];

        Log::info('Processing employee change', [
            'event_type' => $eventType,
            'document_id' => $documentId,
            'has_data' => !empty($documentData)
        ]);

        // Use existing sync service
        $result = $this->syncService->syncOnFirestoreChange(
            $eventType,
            $documentId,
            $documentData
        );

        // Update cache
        \Illuminate\Support\Facades\Cache::put(
            'firestore_listener_last_activity',
            now()->toISOString(),
            now()->addHours(24)
        );

        return $result;
    }

    /**
     * Process permit change
     */
    protected function processPermitChange(array $webhookData): array
    {
        // TODO: Implement permit sync service
        Log::info('Processing permit change', $webhookData);
        
        return [
            'status' => 'processed',
            'message' => 'Permit change processed (not implemented yet)'
        ];
    }

    /**
     * Process presence change
     */
    protected function processPresenceChange(array $webhookData): array
    {
        // TODO: Implement presence sync service
        Log::info('Processing presence change', $webhookData);
        
        return [
            'status' => 'processed',
            'message' => 'Presence change processed (not implemented yet)'
        ];
    }

    /**
     * Test webhook endpoint
     */
    public function testWebhook(Request $request): JsonResponse
    {
        $testData = [
            'event_type' => 'UPDATE',
            'document_id' => 'test_employee_123',
            'document_data' => [
                'name' => 'Test Employee',
                'email' => 'test@example.com',
                'uid' => 'test_uid_123'
            ],
            'collection' => 'employees',
            'timestamp' => now()->toISOString()
        ];

        Log::info('Firestore webhook: Test webhook called');

        $result = $this->processEmployeeChange($testData);

        return response()->json([
            'status' => 'success',
            'message' => 'Test webhook processed',
            'test_data' => $testData,
            'result' => $result
        ]);
    }
}



