<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RealTimeSyncController;
use App\Http\Controllers\Api\FirestoreWebhookController;
use App\Http\Controllers\Api\ManualFirestoreSyncController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Real-time sync routes (protected by auth)
Route::middleware(['auth:sanctum'])->group(function () {
    // Get sync status
    Route::get('/realtime-sync/status', [RealTimeSyncController::class, 'getStatus']);
    
    // Trigger manual sync
    Route::post('/realtime-sync/trigger', [RealTimeSyncController::class, 'triggerSync']);
    
    // Set sync interval
    Route::post('/realtime-sync/interval', [RealTimeSyncController::class, 'setInterval']);
    
    // Get sync statistics
    Route::get('/realtime-sync/stats', [RealTimeSyncController::class, 'getStats']);
    
    // Firestore change listener management
    Route::post('/realtime-sync/listener/start', [RealTimeSyncController::class, 'startListener']);
    Route::post('/realtime-sync/listener/stop', [RealTimeSyncController::class, 'stopListener']);
    Route::get('/realtime-sync/listener/status', [RealTimeSyncController::class, 'getListenerStatus']);
    
    // Simulate Firestore change for testing
    Route::post('/realtime-sync/simulate-change', [RealTimeSyncController::class, 'simulateChange']);
    
    // Manual Firestore sync routes
    Route::prefix('manual-sync')->group(function () {
        // Sync all employees
        Route::post('/all', [ManualFirestoreSyncController::class, 'syncAll']);
        
        // Sync specific employee by UID
        Route::post('/uid', [ManualFirestoreSyncController::class, 'syncByUid']);
        
        // Sync specific employee by email
        Route::post('/email', [ManualFirestoreSyncController::class, 'syncByEmail']);
        
        // Clean up deleted employees
        Route::post('/cleanup', [ManualFirestoreSyncController::class, 'cleanup']);
        
        // Full sync (sync all + cleanup)
        Route::post('/full', [ManualFirestoreSyncController::class, 'fullSync']);
        
        // Get sync status
        Route::get('/status', [ManualFirestoreSyncController::class, 'status']);
        
        // Dry run - show what would be synced
        Route::get('/dry-run', [ManualFirestoreSyncController::class, 'dryRun']);
    });
});

// Firestore webhook routes (no auth required - secured by webhook signature)
Route::prefix('firestore-webhook')->group(function () {
    // Employee changes webhook
    Route::post('/employee-change', [FirestoreWebhookController::class, 'handleEmployeeChange']);
    
    // Permit changes webhook
    Route::post('/permit-change', [FirestoreWebhookController::class, 'handlePermitChange']);
    
    // Presence changes webhook
    Route::post('/presence-change', [FirestoreWebhookController::class, 'handlePresenceChange']);
    
    // Test webhook endpoint
    Route::post('/test', [FirestoreWebhookController::class, 'testWebhook']);
});
