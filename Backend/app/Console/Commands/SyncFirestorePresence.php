<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirestoreService;
use App\Models\Attendance;
use App\Models\Presence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncFirestorePresence extends Command
{
    protected $signature = 'sync:firestore-presence';
    protected $description = 'Sync presence (absensi) data from Firestore to MySQL database';

    public function handle()
    {
        $service = new FirestoreService();
        $absensiData = $service->getAbsensi();
        Log::info('Data absensi yang diambil dari Firestore:', $absensiData);

        foreach ($absensiData as $data) {
        Log::info('Proses data:', $data); // Tambahkan log debug

        Presence::updateOrCreate(
            ['firestore_id' => $data['firestore_id']],
            [
                'uid' => $data['uid'],
                'nama' => $data['nama'],
                'tanggal' => Carbon::parse($data['tanggal'])->format('Y-m-d'),
                'clock_in' => isset($data['clockIn']) ? Carbon::parse($data['clockIn'])->format('Y-m-d H:i:s') : null,
                'foto_clock_in' => $data['fotoClockIn'] ?? null,
                'clock_out' => isset($data['clockOut']) ? Carbon::parse($data['clockOut'])->format('Y-m-d H:i:s') : null,
                'foto_clock_out' => $data['fotoClockOut'] ?? null,
            ]
        );
}
    

        $this->info('✅ Presensi berhasil disinkronkan dari Firestore ke MySQL.');
    }
}
