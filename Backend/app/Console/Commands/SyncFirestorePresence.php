<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirestoreService;
use App\Models\Attendance;
use App\Models\Presence;
use Illuminate\Support\Carbon;

class SyncFirestorePresence extends Command
{
    protected $signature = 'sync:firestore-presence';
    protected $description = 'Sync presence (absensi) data from Firestore to MySQL database';

    public function handle()
    {
        $service = new FirestoreService();
        $absensiData = $service->getAbsensi();

        foreach ($absensiData as $absen) {
            Presence::updateOrCreate(
                [
                    'uid' => $absen['uid'] ?? '',
                    'tanggal' => Carbon::parse($absen['tanggal'] ?? now())->toDateString(),
                ],
                [
                    'nama' => $absen['nama'] ?? '',
                    'clock_in' => isset($absen['clockIn']) ? Carbon::parse($absen['clockIn']) : null,
                    'clock_out' => isset($absen['clockOut']) ? Carbon::parse($absen['clockOut']) : null,
                    'foto_clock_in' => $absen['fotoClockIn'] ?? null,
                    'foto_clock_out' => $absen['fotoClockOut'] ?? null,
                ]
            );
        }

        $this->info('✅ Presensi berhasil disinkronkan dari Firestore ke MySQL.');
    }
}
