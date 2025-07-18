<?php

namespace App\Filament\Resources\PresenceResource\Pages;

use App\Filament\Resources\PresenceResource;
use App\Models\Presence;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Filament\Actions\Action;
use App\Services\FirestoreService;
use Filament\Notifications\Notification;
     
class ListPresences extends ListRecords
{
    protected static string $resource = PresenceResource::class;

    public function getTitle(): string
    {
        return 'Presensi Karyawan';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Muat Data Presensi')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->requiresConfirmation('Apakah Anda yakin ingin memuat ulang data presensi dari Firestore?')
                ->modalHeading('Muat Ulang Data Presensi?')
                ->modalSubheading('Ini akan menghapus data presensi yang ada dan menggantinya dengan data terbaru dari Firestore.')
                ->modalButton('Muat Ulang')
                ->action(function () {
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
                    Notification::make()
                    ->title('✅ Data presensi berhasil disinkronkan!')
                    ->success()
                    ->send();
                }),
        ];
        
    }
}
