<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirestoreService;
use App\Models\Employee;

class AddDummyFcmTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:add-dummy-token 
                            {--employee-id=51 : Employee ID to add token for}
                            {--token= : FCM token to add (if not provided, will generate dummy)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add dummy FCM token to Firestore for testing';

    protected $firestoreService;

    public function __construct(FirestoreService $firestoreService)
    {
        parent::__construct();
        $this->firestoreService = $firestoreService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $employeeId = $this->option('employee-id');
        $fcmToken = $this->option('token');

        $this->info("🔑 Adding Dummy FCM Token");
        $this->info("========================");

        // Get employee
        $employee = Employee::find($employeeId);
        if (!$employee) {
            $this->error("❌ Employee dengan ID {$employeeId} tidak ditemukan!");
            return Command::FAILURE;
        }

        $this->info("✅ Employee: {$employee->name} (ID: {$employee->id})");
        $this->info("UID: {$employee->uid}");

        // Generate dummy token if not provided
        if (!$fcmToken) {
            $fcmToken = 'dummy_fcm_token_' . time() . '_' . $employee->id;
            $this->info("🔑 Generated dummy token: {$fcmToken}");
        } else {
            $this->info("🔑 Using provided token: {$fcmToken}");
        }

        try {
            // Add token to Firestore
            $result = $this->firestoreService->addEmployeeFcmToken(
                $employee->uid,
                $fcmToken,
                'test_device_' . $employee->id,
                'android'
            );

            if ($result) {
                $this->info("✅ FCM token berhasil ditambahkan ke Firestore!");
                $this->info("📱 Token ID: {$result}");
                
                $this->newLine();
                $this->info("🧪 Sekarang Anda bisa test dengan:");
                $this->info("php artisan fcm:test-firestore-tokens --employee-uid={$employee->uid}");
                $this->info("php artisan notifications:automated-presence --type=check-in --time=08:00 --test");
                
                return Command::SUCCESS;
            } else {
                $this->error("❌ Gagal menambahkan FCM token ke Firestore");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
