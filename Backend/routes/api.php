<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\NotificationController;

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
});
