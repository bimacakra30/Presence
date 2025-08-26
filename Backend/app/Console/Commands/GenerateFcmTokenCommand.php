<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;

class GenerateFcmTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:generate-token {email : Email user untuk generate token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate FCM token untuk testing dari Firebase Auth';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🔑 Generating FCM token for email: {$email}");
        
        try {
            // Initialize Firebase
            $factory = (new Factory)
                ->withServiceAccount(base_path('storage/app/firebase/firebase_credentials.json'));
            
            $auth = $factory->createAuth();
            
            // Get user by email
            $user = $auth->getUserByEmail($email);
            
            if (!$user) {
                $this->error("❌ User dengan email {$email} tidak ditemukan di Firebase Auth");
                return Command::FAILURE;
            }
            
            $this->info("✅ User found: {$user->displayName} (UID: {$user->uid})");
            
            // Generate custom token (untuk testing)
            $customToken = $auth->createCustomToken($user->uid);
            
            $this->info("✅ Custom token generated successfully!");
            $this->info("🔑 Custom Token: {$customToken}");
            
            // Note: Untuk mendapatkan FCM token yang sebenarnya,
            // mobile app harus menggunakan custom token ini untuk authenticate
            // dan kemudian request FCM token dari Firebase
            
            $this->newLine();
            $this->warn("⚠️  Note:");
            $this->warn("   - Custom token ini digunakan untuk authenticate di mobile app");
            $this->warn("   - Mobile app harus menggunakan token ini untuk request FCM token");
            $this->warn("   - FCM token akan berbeda untuk setiap device");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
