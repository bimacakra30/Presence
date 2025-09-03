<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationMonitoringController;
use App\Http\Controllers\Api\RealTimeSyncController;

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

// Notification routes (protected by auth)
Route::middleware(['auth:sanctum'])->group(function () {
    // Get notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    
    // Get single notification
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    
    // Mark notification as read
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    
    // Mark multiple notifications as read
    Route::patch('/notifications/mark-read', [NotificationController::class, 'markMultipleAsRead']);
    
    // Mark all notifications as read
    Route::patch('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    
    // Update FCM token
    Route::post('/notifications/fcm-token', [NotificationController::class, 'updateFcmToken']);
    
    // Get notification statistics
    Route::get('/notifications/statistics', [NotificationController::class, 'statistics']);
    
    // Delete notification
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    
    // Delete multiple notifications
    Route::delete('/notifications', [NotificationController::class, 'destroyMultiple']);
    
    // FCM Token Management with Firestore
    Route::post('/notifications/fcm-token/firestore', [NotificationController::class, 'addFcmTokenToFirestore']);
    Route::delete('/notifications/fcm-token/firestore', [NotificationController::class, 'removeFcmTokenFromFirestore']);
    Route::get('/notifications/fcm-tokens/firestore', [NotificationController::class, 'getFcmTokensFromFirestore']);
    
    // Test notification with Firestore tokens
    Route::post('/notifications/test-firestore', [NotificationController::class, 'sendTestNotificationWithFirestoreTokens']);
});

// Monitoring routes (protected by auth)
Route::middleware(['auth:sanctum'])->group(function () {
    // Dashboard overview
    Route::get('/monitoring/dashboard', [NotificationMonitoringController::class, 'dashboard']);
    
    // Notification statistics
    Route::get('/monitoring/notifications/stats', [NotificationMonitoringController::class, 'notificationStats']);
    Route::get('/monitoring/notifications/breakdown-by-type', [NotificationMonitoringController::class, 'breakdownByType']);
    Route::get('/monitoring/notifications/hourly-distribution', [NotificationMonitoringController::class, 'hourlyDistribution']);
    Route::get('/monitoring/notifications/top-recipients', [NotificationMonitoringController::class, 'topRecipients']);
    Route::get('/monitoring/notifications/recent', [NotificationMonitoringController::class, 'recentNotifications']);
    
    // FCM token statistics
    Route::get('/monitoring/fcm-tokens/stats', [NotificationMonitoringController::class, 'fcmTokenStats']);
    
    // System statistics
    Route::get('/monitoring/system/stats', [NotificationMonitoringController::class, 'systemStats']);
    
    // Alerts and recommendations
    Route::get('/monitoring/alerts', [NotificationMonitoringController::class, 'alerts']);
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
});
