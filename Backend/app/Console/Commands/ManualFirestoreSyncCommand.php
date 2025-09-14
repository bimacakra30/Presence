<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirestoreSyncService;
use App\Services\FirestoreService;
use App\Models\Employee;

class ManualFirestoreSyncCommand extends Command
{
    protected $signature = 'firestore:manual-sync 
                            {--all : Sync all employees from Firestore}
                            {--uid= : Sync specific employee by UID}
                            {--email= : Sync specific employee by email}
                            {--cleanup : Clean up deleted employees}
                            {--force : Force sync even if no changes detected}
                            {--dry-run : Show what would be synced without making changes}
                            {--status : Show sync status}';

    protected $description = 'Manual sync employees from Firestore to local database';

    protected $firestoreSyncService;
    protected $firestoreService;

    public function __construct()
    {
        parent::__construct();
        $this->firestoreSyncService = new FirestoreSyncService();
        $this->firestoreService = new FirestoreService();
    }

    public function handle()
    {
        $this->info('🔄 Manual Firestore Employee Sync');
        $this->info('================================');

        if ($this->option('all')) {
            return $this->syncAllEmployees();
        }

        if ($this->option('uid')) {
            return $this->syncEmployeeByUid($this->option('uid'));
        }

        if ($this->option('email')) {
            return $this->syncEmployeeByEmail($this->option('email'));
        }

        if ($this->option('cleanup')) {
            return $this->cleanupDeletedEmployees();
        }

        if ($this->option('dry-run')) {
            return $this->dryRunSyncAll();
        }

        if ($this->option('status')) {
            return $this->showStatus();
        }

        // Default: show help
        $this->showHelp();
        return 0;
    }

    protected function syncAllEmployees()
    {
        $this->info('📥 Syncing all employees from Firestore...');
        
        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            return $this->dryRunSyncAll();
        }

        try {
            $startTime = now();
            $result = $this->firestoreSyncService->syncAllEmployees();
            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);

            $this->displaySyncResults($result, $duration);

            if (count($result['errors']) > 0) {
                $this->displayErrors($result['errors']);
                return 1;
            }

            $this->info('✅ Sync completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            return 1;
        }
    }

    protected function syncEmployeeByUid(string $uid)
    {
        $this->info("📥 Syncing employee with UID: {$uid}");

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            return $this->dryRunSyncByUid($uid);
        }

        try {
            $result = $this->firestoreSyncService->syncEmployeeByUid($uid);
            
            $this->info("✅ Employee sync completed!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['Action', $result['action']],
                    ['Employee', $result['employee']->name ?? 'N/A'],
                    ['Email', $result['employee']->email ?? 'N/A'],
                    ['UID', $result['employee']->uid ?? 'N/A'],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            return 1;
        }
    }

    protected function syncEmployeeByEmail(string $email)
    {
        $this->info("📥 Syncing employee with email: {$email}");

        try {
            // Cari employee di Firestore berdasarkan email
            $firestoreUsers = $this->firestoreService->searchUsers('email', $email);
            
            if (empty($firestoreUsers)) {
                $this->error("❌ Employee with email '{$email}' not found in Firestore");
                return 1;
            }

            $result = $this->firestoreSyncService->syncSingleEmployee($firestoreUsers[0]);
            
            $this->info("✅ Employee sync completed!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['Action', $result['action']],
                    ['Employee', $result['employee']->name ?? 'N/A'],
                    ['Email', $result['employee']->email ?? 'N/A'],
                    ['UID', $result['employee']->uid ?? 'N/A'],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            return 1;
        }
    }

    protected function cleanupDeletedEmployees()
    {
        $this->info('🧹 Cleaning up deleted employees...');

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            return $this->dryRunCleanup();
        }

        try {
            $result = $this->firestoreSyncService->cleanupDeletedEmployees();
            
            $this->info("✅ Cleanup completed!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['Deleted Count', $result['deleted']],
                    ['Deleted Employees', implode(', ', $result['employees'])],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Cleanup failed: ' . $e->getMessage());
            return 1;
        }
    }

    protected function dryRunSyncAll()
    {
        try {
            $firestoreUsers = $this->firestoreService->getUsers(false);
            $this->info("📊 Found " . count($firestoreUsers) . " employees in Firestore");

            $localEmployees = Employee::all()->keyBy('uid');
            $this->info("📊 Found {$localEmployees->count()} employees in local database");

            $toCreate = 0;
            $toUpdate = 0;
            $noChange = 0;

            foreach ($firestoreUsers as $firestoreUser) {
                $uid = $firestoreUser['uid'] ?? null;
                $localEmployee = $localEmployees->get($uid);

                if (!$localEmployee) {
                    $toCreate++;
                } else {
                    // Check for changes
                    $syncData = $this->prepareEmployeeDataForComparison($firestoreUser);
                    if ($this->hasDataChanges($localEmployee, $syncData)) {
                        $toUpdate++;
                    } else {
                        $noChange++;
                    }
                }
            }

            $this->table(
                ['Action', 'Count'],
                [
                    ['To Create', $toCreate],
                    ['To Update', $toUpdate],
                    ['No Changes', $noChange],
                    ['Total', count($firestoreUsers)],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Dry run failed: ' . $e->getMessage());
            return 1;
        }
    }

    protected function dryRunSyncByUid(string $uid)
    {
        try {
            $firestoreUsers = $this->firestoreService->searchUsers('uid', $uid);
            
            if (empty($firestoreUsers)) {
                $this->error("❌ Employee with UID '{$uid}' not found in Firestore");
                return 1;
            }

            $firestoreUser = $firestoreUsers[0];
            $localEmployee = Employee::where('uid', $uid)->first();

            if (!$localEmployee) {
                $this->info("📝 Would CREATE new employee: {$firestoreUser['name']} ({$firestoreUser['email']})");
            } else {
                $syncData = $this->prepareEmployeeDataForComparison($firestoreUser);
                if ($this->hasDataChanges($localEmployee, $syncData)) {
                    $this->info("📝 Would UPDATE employee: {$localEmployee->name} ({$localEmployee->email})");
                    $this->displayChanges($localEmployee, $syncData);
                } else {
                    $this->info("📝 No changes needed for employee: {$localEmployee->name} ({$localEmployee->email})");
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Dry run failed: ' . $e->getMessage());
            return 1;
        }
    }

    protected function dryRunCleanup()
    {
        try {
            $firestoreUsers = $this->firestoreService->getUsers(false);
            $firestoreUids = collect($firestoreUsers)->pluck('uid')->filter()->toArray();
            
            $deletedEmployees = Employee::whereNotNull('uid')
                ->whereNotIn('uid', $firestoreUids)
                ->get();

            $this->info("📊 Found {$deletedEmployees->count()} employees to delete");

            if ($deletedEmployees->count() > 0) {
                $this->table(
                    ['Name', 'Email', 'UID'],
                    $deletedEmployees->map(function ($emp) {
                        return [$emp->name, $emp->email, $emp->uid];
                    })->toArray()
                );
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Dry run cleanup failed: ' . $e->getMessage());
            return 1;
        }
    }

    protected function displaySyncResults(array $result, int $duration)
    {
        $this->info("⏱️  Sync completed in {$duration} seconds");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Synced', $result['synced']],
                ['Created', $result['created']],
                ['Updated', $result['updated']],
                ['Errors', count($result['errors'])],
            ]
        );
    }

    protected function displayErrors(array $errors)
    {
        $this->error('❌ Errors occurred during sync:');
        $this->table(
            ['Employee', 'Error'],
            collect($errors)->map(function ($error) {
                return [$error['user'], $error['error']];
            })->toArray()
        );
    }

    protected function displayChanges($employee, array $newData)
    {
        $fieldsToCheck = ['name', 'username', 'email', 'phone', 'address', 'position', 'status', 'provider', 'photo'];

        $changes = [];
        foreach ($fieldsToCheck as $field) {
            $currentValue = $employee->getAttribute($field);
            $newValue = $newData[$field] ?? null;

            if (empty($currentValue)) $currentValue = null;
            if (empty($newValue)) $newValue = null;

            if ($currentValue !== $newValue) {
                $changes[] = [$field, $currentValue, $newValue];
            }
        }

        if (!empty($changes)) {
            $this->table(['Field', 'Current', 'New'], $changes);
        }
    }

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

    protected function showStatus()
    {
        $this->info('📊 Sync Status');
        $this->info('==============');
        
        try {
            $localCount = Employee::count();
            $firestoreUsers = $this->firestoreService->getUsers(false);
            $firestoreCount = count($firestoreUsers);
            
            $syncedCount = Employee::whereNotNull('firestore_id')->count();
            $unsyncedCount = $localCount - $syncedCount;
            
            $recentActivity = Employee::where('updated_at', '>=', now()->subDay())->count();
            
            $this->table(
                ['Property', 'Value'],
                [
                    ['Local employees', $localCount],
                    ['Firestore employees', $firestoreCount],
                    ['Synced employees', $syncedCount],
                    ['Unsynced employees', $unsyncedCount],
                    ['Sync percentage', $localCount > 0 ? round(($syncedCount / $localCount) * 100, 2) . '%' : '0%'],
                    ['Recent activity (24h)', $recentActivity],
                ]
            );
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to get status: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    protected function showHelp()
    {
        $this->info('📖 Available options:');
        $this->line('');
        $this->line('  --all              Sync all employees from Firestore');
        $this->line('  --uid=UID          Sync specific employee by UID');
        $this->line('  --email=EMAIL      Sync specific employee by email');
        $this->line('  --cleanup          Clean up deleted employees');
        $this->line('  --force            Force sync even if no changes detected');
        $this->line('  --dry-run          Show what would be synced without making changes');
        $this->line('  --status           Show sync status');
        $this->line('');
        $this->line('Examples:');
        $this->line('  php artisan firestore:manual-sync --all');
        $this->line('  php artisan firestore:manual-sync --uid=abc123');
        $this->line('  php artisan firestore:manual-sync --email=user@example.com');
        $this->line('  php artisan firestore:manual-sync --all --dry-run');
        $this->line('  php artisan firestore:manual-sync --status');
    }
}
