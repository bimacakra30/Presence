<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirestoreSyncService;
use App\Services\FirestoreService;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ManualFirestoreSyncController extends Controller
{
    protected $firestoreSyncService;
    protected $firestoreService;

    public function __construct()
    {
        $this->firestoreSyncService = new FirestoreSyncService();
        $this->firestoreService = new FirestoreService();
    }

    /**
     * Sync all employees from Firestore
     */
    public function syncAll(Request $request): JsonResponse
    {
        try {
            $startTime = now();
            $result = $this->firestoreSyncService->syncAllEmployees();
            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);

            Log::info('Manual sync all completed', [
                'duration' => $duration,
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sync completed successfully',
                'data' => [
                    'duration_seconds' => $duration,
                    'synced' => $result['synced'],
                    'created' => $result['created'],
                    'updated' => $result['updated'],
                    'errors' => $result['errors'],
                    'error_count' => count($result['errors'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Manual sync all failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Sync specific employee by UID
     */
    public function syncByUid(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'uid' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $uid = $request->input('uid');
            $result = $this->firestoreSyncService->syncEmployeeByUid($uid);

            Log::info('Manual sync by UID completed', [
                'uid' => $uid,
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee sync completed successfully',
                'data' => [
                    'action' => $result['action'],
                    'employee' => [
                        'id' => $result['employee']->id,
                        'name' => $result['employee']->name,
                        'email' => $result['employee']->email,
                        'uid' => $result['employee']->uid,
                        'firestore_id' => $result['employee']->firestore_id
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Manual sync by UID failed', [
                'uid' => $request->input('uid'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Sync specific employee by email
     */
    public function syncByEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $email = $request->input('email');
            
            // Cari employee di Firestore berdasarkan email
            $firestoreUsers = $this->firestoreService->searchUsers('email', $email);
            
            if (empty($firestoreUsers)) {
                return response()->json([
                    'success' => false,
                    'message' => "Employee with email '{$email}' not found in Firestore",
                    'data' => null
                ], 404);
            }

            $result = $this->firestoreSyncService->syncSingleEmployee($firestoreUsers[0]);

            Log::info('Manual sync by email completed', [
                'email' => $email,
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee sync completed successfully',
                'data' => [
                    'action' => $result['action'],
                    'employee' => [
                        'id' => $result['employee']->id,
                        'name' => $result['employee']->name,
                        'email' => $result['employee']->email,
                        'uid' => $result['employee']->uid,
                        'firestore_id' => $result['employee']->firestore_id
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Manual sync by email failed', [
                'email' => $request->input('email'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Clean up deleted employees
     */
    public function cleanup(Request $request): JsonResponse
    {
        try {
            $result = $this->firestoreSyncService->cleanupDeletedEmployees();

            Log::info('Manual cleanup completed', [
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cleanup completed successfully',
                'data' => [
                    'deleted_count' => $result['deleted'],
                    'deleted_employees' => $result['employees']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Manual cleanup failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Full sync (sync all + cleanup)
     */
    public function fullSync(Request $request): JsonResponse
    {
        try {
            $startTime = now();
            $result = $this->firestoreSyncService->fullSync();
            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);

            Log::info('Manual full sync completed', [
                'duration' => $duration,
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Full sync completed successfully',
                'data' => [
                    'duration_seconds' => $duration,
                    'synced' => $result['synced'],
                    'created' => $result['created'],
                    'updated' => $result['updated'],
                    'deleted' => $result['deleted'],
                    'errors' => $result['errors'],
                    'error_count' => count($result['errors']),
                    'deleted_employees' => $result['deleted_employees']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Manual full sync failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Full sync failed: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get sync status and statistics
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $localCount = Employee::count();
            $firestoreUsers = $this->firestoreService->getUsers(false);
            $firestoreCount = count($firestoreUsers);

            // Get employees with firestore_id
            $syncedCount = Employee::whereNotNull('firestore_id')->count();
            $unsyncedCount = $localCount - $syncedCount;

            // Get recent sync activity (last 24 hours)
            $recentActivity = Employee::where('updated_at', '>=', now()->subDay())
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Status retrieved successfully',
                'data' => [
                    'local_employees' => $localCount,
                    'firestore_employees' => $firestoreCount,
                    'synced_employees' => $syncedCount,
                    'unsynced_employees' => $unsyncedCount,
                    'recent_activity_24h' => $recentActivity,
                    'sync_percentage' => $localCount > 0 ? round(($syncedCount / $localCount) * 100, 2) : 0
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get sync status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get status: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Dry run - show what would be synced without making changes
     */
    public function dryRun(Request $request): JsonResponse
    {
        try {
            $firestoreUsers = $this->firestoreService->getUsers(false);
            $localEmployees = Employee::all()->keyBy('uid');

            $toCreate = 0;
            $toUpdate = 0;
            $noChange = 0;
            $changes = [];

            foreach ($firestoreUsers as $firestoreUser) {
                $uid = $firestoreUser['uid'] ?? null;
                $localEmployee = $localEmployees->get($uid);

                if (!$localEmployee) {
                    $toCreate++;
                    $changes[] = [
                        'action' => 'create',
                        'employee' => [
                            'name' => $firestoreUser['name'] ?? 'N/A',
                            'email' => $firestoreUser['email'] ?? 'N/A',
                            'uid' => $uid
                        ]
                    ];
                } else {
                    $syncData = $this->prepareEmployeeDataForComparison($firestoreUser);
                    if ($this->hasDataChanges($localEmployee, $syncData)) {
                        $toUpdate++;
                        $changes[] = [
                            'action' => 'update',
                            'employee' => [
                                'id' => $localEmployee->id,
                                'name' => $localEmployee->name,
                                'email' => $localEmployee->email,
                                'uid' => $localEmployee->uid
                            ],
                            'changes' => $this->getChangeDetails($localEmployee, $syncData)
                        ];
                    } else {
                        $noChange++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Dry run completed successfully',
                'data' => [
                    'summary' => [
                        'to_create' => $toCreate,
                        'to_update' => $toUpdate,
                        'no_change' => $noChange,
                        'total_firestore' => count($firestoreUsers),
                        'total_local' => $localEmployees->count()
                    ],
                    'changes' => $changes
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Dry run failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dry run failed: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Prepare employee data for comparison
     */
    protected function prepareEmployeeDataForComparison(array $firestoreUser): array
    {
        return [
            'name' => $firestoreUser['name'] ?? '',
            'username' => $firestoreUser['username'] ?? '',
            'email' => $firestoreUser['email'] ?? '',
            'phone' => $firestoreUser['phone'] ?? '',
            'address' => $firestoreUser['address'] ?? '',
            'position' => $firestoreUser['position'] ?? '',
            'status' => $firestoreUser['status'] ?? '',
            'provider' => $firestoreUser['provider'] ?? '',
            'photo' => $firestoreUser['profilePictureUrl'] ?? '',
            'date_of_birth' => !empty($firestoreUser['dateOfBirth']) ? $firestoreUser['dateOfBirth'] : null,
        ];
    }

    /**
     * Check if there are data changes
     */
    protected function hasDataChanges($employee, array $newData): bool
    {
        $fieldsToCheck = ['name', 'username', 'email', 'phone', 'address', 'position', 'status', 'provider', 'photo'];

        foreach ($fieldsToCheck as $field) {
            $currentValue = $employee->getAttribute($field);
            $newValue = $newData[$field] ?? null;

            if (empty($currentValue)) $currentValue = null;
            if (empty($newValue)) $newValue = null;

            if ($currentValue !== $newValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get detailed change information
     */
    protected function getChangeDetails($employee, array $newData): array
    {
        $changes = [];
        $fieldsToCheck = ['name', 'username', 'email', 'phone', 'address', 'position', 'status', 'provider', 'photo'];

        foreach ($fieldsToCheck as $field) {
            $currentValue = $employee->getAttribute($field);
            $newValue = $newData[$field] ?? null;

            if (empty($currentValue)) $currentValue = null;
            if (empty($newValue)) $newValue = null;

            if ($currentValue !== $newValue) {
                $changes[$field] = [
                    'from' => $currentValue,
                    'to' => $newValue
                ];
            }
        }

        return $changes;
    }
}

