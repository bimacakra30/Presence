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
}
