<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;

class TestFirebaseAuthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:test-auth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Firebase authentication';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🔥 Firebase Authentication Test");
        $this->info("==============================");

        // Check credentials file
        $credentialsPath = base_path('storage/app/firebase/firebase_credentials.json');
        
        if (!file_exists($credentialsPath)) {
            $this->error("❌ Firebase credentials file not found: {$credentialsPath}");
            return Command::FAILURE;
        }

        $this->info("✅ Credentials file exists: {$credentialsPath}");

        // Read credentials
        try {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            
            if (!$credentials) {
                $this->error("❌ Invalid JSON in credentials file");
                return Command::FAILURE;
            }

            $this->info("✅ Credentials file is valid JSON");
            $this->info("📧 Client Email: " . ($credentials['client_email'] ?? 'NOT FOUND'));
            $this->info("🏢 Project ID: " . ($credentials['project_id'] ?? 'NOT FOUND'));
            $this->info("🔑 Private Key: " . (isset($credentials['private_key']) ? 'FOUND' : 'NOT FOUND'));

        } catch (\Exception $e) {
            $this->error("❌ Error reading credentials: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Test JWT creation
        $this->info("\n🔐 Testing JWT Creation...");
        try {
            $jwt = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => time() + 3600,
                'iat' => time()
            ], $credentials['private_key'], 'RS256');
            
            $this->info("✅ JWT created successfully");
            $this->info("🔑 JWT Length: " . strlen($jwt) . " characters");
        } catch (\Exception $e) {
            $this->error("❌ Error creating JWT: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Test access token
        $this->info("\n🎫 Testing Access Token...");
        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]);
            
            if ($response->successful()) {
                $accessToken = $response->json('access_token');
                $this->info("✅ Access token obtained successfully");
                $this->info("🔑 Token Length: " . strlen($accessToken) . " characters");
            } else {
                throw new \Exception('Failed to get access token: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error("❌ Error getting access token: " . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info("\n🎉 Firebase authentication test completed successfully!");
        return Command::SUCCESS;
    }


}
