<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PresenceSyncService;
use App\Services\FirestoreService;

class SyncPresenceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'presence:sync {--force : Force sync even if data exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data presensi dari Firestore collection presence';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai sinkronisasi data presensi dari Firestore...');
        
        try {
            $firestoreService = new FirestoreService();
            $presenceSyncService = new PresenceSyncService($firestoreService);
            
            $force = $this->option('force');
            
            $this->info('Mengambil data dari collection "presence" di Firestore...');
            $result = $presenceSyncService->syncAllPresenceData($force);
            
            $this->info('Sinkronisasi selesai!');
            $this->line('');
            $this->info('Hasil sinkronisasi:');
            $this->line("• Data baru: {$result['created']}");
            $this->line("• Data diperbarui: {$result['updated']}");
            $this->line("• Data tidak berubah: {$result['no_change']}");
            $this->line("• Error: {$result['error_count']}");
            $this->line("• Durasi: {$result['duration_seconds']} detik");
            
            if (!empty($result['errors'])) {
                $this->line('');
                $this->warn('Detail error:');
                foreach ($result['errors'] as $error) {
                    $this->line("• {$error['firestore_id']}: {$error['error']}");
                }
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
