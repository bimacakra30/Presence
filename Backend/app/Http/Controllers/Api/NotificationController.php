<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Employee;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get notifications for authenticated user/employee
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $employee = null;

        // Check if user is employee
        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            // Try to find employee by user ID
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $query = Notification::where('recipient_type', Employee::class)
            ->where('recipient_id', $employee->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by read status
        if ($request->has('read')) {
            if ($request->read === 'true') {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => Notification::where('recipient_type', Employee::class)
                ->where('recipient_id', $employee->id)
                ->whereNull('read_at')
                ->count()
        ]);
    }

    /**
     * Get single notification
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        $employee = null;

        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $notification = Notification::where('id', $id)
            ->where('recipient_type', Employee::class)
            ->where('recipient_id', $employee->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $notification
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id): JsonResponse
    {
        $user = Auth::user();
        $employee = null;

        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $notification = Notification::where('id', $id)
            ->where('recipient_type', Employee::class)
            ->where('recipient_id', $employee->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark multiple notifications as read
     */
    public function markMultipleAsRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'integer|exists:notifications,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $employee = null;

        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $updated = Notification::whereIn('id', $request->notification_ids)
            ->where('recipient_type', Employee::class)
            ->where('recipient_id', $employee->id)
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} notifications marked as read"
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        $employee = null;

        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $updated = Notification::where('recipient_type', Employee::class)
            ->where('recipient_id', $employee->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} notifications marked as read"
        ]);
    }

    /**
     * Update FCM token
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $employee = null;

        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $this->notificationService->updateFcmToken($employee, $request->fcm_token);

        return response()->json([
            'success' => true,
            'message' => 'FCM token updated successfully'
        ]);
    }

    /**
     * Get notification statistics
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        $employee = null;

        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $stats = [
            'total' => Notification::where('recipient_type', Employee::class)
                ->where('recipient_id', $employee->id)->count(),
            'unread' => Notification::where('recipient_type', Employee::class)
                ->where('recipient_id', $employee->id)
                ->whereNull('read_at')->count(),
            'read' => Notification::where('recipient_type', Employee::class)
                ->where('recipient_id', $employee->id)
                ->whereNotNull('read_at')->count(),
            'by_type' => Notification::where('recipient_type', Employee::class)
                ->where('recipient_id', $employee->id)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $employee = null;

        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $notification = Notification::where('id', $id)
            ->where('recipient_type', Employee::class)
            ->where('recipient_id', $employee->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }

    /**
     * Delete multiple notifications
     */
    public function destroyMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'integer|exists:notifications,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $employee = null;

        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $deleted = Notification::whereIn('id', $request->notification_ids)
            ->where('recipient_type', Employee::class)
            ->where('recipient_id', $employee->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} notifications deleted successfully"
        ]);
    }

    /**
     * Add FCM token to Firestore
     */
    public function addFcmTokenToFirestore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:500',
            'device_id' => 'nullable|string|max:255',
            'platform' => 'nullable|string|in:android,ios,web,unknown'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $employee = null;

        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        try {
            $tokenId = $this->notificationService->addFcmTokenToFirestore(
                $employee->uid,
                $request->fcm_token,
                $request->device_id,
                $request->platform ?? 'unknown'
            );

            return response()->json([
                'success' => true,
                'message' => 'FCM token added to Firestore successfully',
                'data' => [
                    'token_id' => $tokenId,
                    'employee_uid' => $employee->uid
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add FCM token to Firestore: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove FCM token from Firestore
     */
    public function removeFcmTokenFromFirestore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $employee = null;

        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        try {
            $result = $this->notificationService->removeFcmTokenFromFirestore(
                $employee->uid,
                $request->token_id
            );

            return response()->json([
                'success' => true,
                'message' => 'FCM token removed from Firestore successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove FCM token from Firestore: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get FCM tokens from Firestore for current employee
     */
    public function getFcmTokensFromFirestore(): JsonResponse
    {
        $user = Auth::user();
        $employee = null;

        if ($user instanceof Employee) {
            $employee = $user;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
        }

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        try {
            $tokens = $this->notificationService->getEmployeeFcmTokensFromFirestore($employee->uid);

            return response()->json([
                'success' => true,
                'data' => $tokens
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get FCM tokens from Firestore: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send test notification using Firestore tokens
     */
    public function sendTestNotificationWithFirestoreTokens(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'employee_uid' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->notificationService->sendToEmployeeWithFirestoreTokens(
                $request->employee_uid,
                $request->title,
                $request->body,
                [
                    'action' => 'test_notification',
                    'timestamp' => now()->toISOString(),
                    'source' => 'api_test'
                ],
                [
                    'type' => Notification::TYPE_GENERAL,
                    'priority' => Notification::PRIORITY_NORMAL
                ]
            );

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Test notification sent successfully' : 'Failed to send test notification'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification: ' . $e->getMessage()
            ], 500);
        }
    }
}
