<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirestoreService;
use App\Models\Employee;

class SyncFirestoreEmployees extends Command
{
    protected $signature = 'sync:firestore-employees';
    protected $description = 'Sync employees from Firestore to local database';

    public function handle()
    {
        $service = new FirestoreService();
        $users = $service->getUsers();

        foreach ($users as $user) {
            $firestoreId = $user['id'] ?? null;

            $employee = Employee::updateOrCreate(
                [
                    'email' => $user['email'],
                ],
                [
                    'name' => $user['username'] ?? 'No Name',
                    'email' => $user['email'],
                    'firestore_id' => $firestoreId,
                    'created_at' => isset($user['createdAt']) ? now()->parse($user['createdAt']) : now(),

                    'phone' => null,
                    'address' => null,
                    'date_of_birth' => null,
                    'position' => null,
                    'salary' => null,
                    'photo' => null,
                ]
            );

            if (!$employee->firestore_id && $firestoreId) {
                $employee->forceFill(['firestore_id' => $firestoreId])->save();
            }
        }

        $this->info('✅ Employees synced successfully from Firestore!');
    }
}
