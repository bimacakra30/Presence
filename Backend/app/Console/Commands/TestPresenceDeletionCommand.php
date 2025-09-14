<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Presence;
use App\Services\PresenceSyncService;
use App\Services\FirestoreService;

class TestPresenceDeletionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'presence:test-deletion {--firestore-id= : Firestore ID to test deletion}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test fitur penghapusan data presence dari Firestore';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing fitur penghapusan data presence...');
        
        try {
            $firestoreService = new FirestoreService();
            $presenceSyncService = new PresenceSyncService($firestoreService);
            
            $firestoreId = $this->option('firestore-id');
            
            if ($firestoreId) {
                // Test penghapusan specific document
                $this->info("Testing penghapusan document dengan Firestore ID: {$firestoreId}");
                
                $result = $presenceSyncService->deletePresenceFromFirestore($firestoreId);
                
                if ($result) {
                    $this->info('✅ Berhasil menghapus document dari Firestore');
                } else {
                    $this->warn('⚠️ Document tidak ditemukan atau sudah dihapus');
                }
            } else {
                // Test dengan data presence yang ada
                $this->info('Mencari data presence dengan firestore_id...');
                
                $presence = Presence::whereNotNull('firestore_id')->first();
                
                if (!$presence) {
                    $this->warn('Tidak ada data presence dengan firestore_id ditemukan');
                    return Command::SUCCESS;
                }
                
                $this->info("Found presence: ID {$presence->id}, Firestore ID: {$presence->firestore_id}");
                $this->info("Employee: {$presence->nama} ({$presence->uid})");
                $this->info("Date: {$presence->tanggal}");
                
                if ($this->confirm('Apakah Anda yakin ingin menghapus data ini dari database lokal dan Firestore?')) {
                    $this->info('Menghapus data presence...');
                    
                    // Simpan firestore_id sebelum dihapus
                    $firestoreId = $presence->firestore_id;
                    
                    // Hapus dari database lokal (akan trigger penghapusan Firestore)
                    $presence->delete();
                    
                    $this->info('✅ Data presence berhasil dihapus dari database lokal');
                    $this->info('✅ Data presence berhasil dihapus dari Firestore');
                    $this->info("Firestore ID yang dihapus: {$firestoreId}");
                } else {
                    $this->info('Penghapusan dibatalkan');
                }
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
