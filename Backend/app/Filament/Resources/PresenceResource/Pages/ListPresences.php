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
use Filament\Tables\Actions\DeleteAction;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Filament\Tables;
use Illuminate\Support\Facades\Log;

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
                ->requiresConfirmation('Apakah Anda yakin ingin memuat ulang data presensi?')
                ->modalHeading('Muat Ulang Data Presensi?')
                ->modalSubheading('Ini akan menghapus data presensi yang ada dan menggantinya dengan data terbaru dari Firestore.')
                ->modalButton('Muat Ulang')
                ->action(function () {
                    $service = new FirestoreService();
                    $absensiData = $service->getAbsensi();

                    foreach ($absensiData as $absen) {
                        Presence::updateOrCreate(
                            [
                                'firestore_id' => $absen['firestore_id'] ?? null,
                                'uid' => $absen['uid'] ?? '',
                                'tanggal' => Carbon::parse($absen['tanggal'] ?? now())->toDateString(),
                            ],
                            [
                                'nama' => $absen['nama'] ?? '',
                                'clock_in' => isset($absen['clockIn']) ? Carbon::parse($absen['clockIn']) : null,
                                'public_id_clock_in' => $absen['fotoClockInPublicId'] ?? null,
                                'public_id_clock_out' => $absen['fotoClockOutPublicId'] ?? null,
                                'clock_out' => isset($absen['clockOut']) ? Carbon::parse($absen['clockOut']) : null,
                                'foto_clock_in' => $absen['fotoClockIn'] ?? null,
                                'foto_clock_out' => $absen['fotoClockOut'] ?? null,
                                'status' => $absen['terlambat'] ?? true, // Default to true if not set
                            ]
                        );
                    }
                    Notification::make()
                    ->title('Data presensi berhasil disinkronkan!')
                    ->success()
                    ->send();
                }),
        ];
        
    }

    protected function getTableActions(): array
    {
        return [
            DeleteAction::make()
            ->before(function (Presence $record) {
                // Konfigurasi ulang Cloudinary secara eksplisit
                Cloudinary::config([
                    'cloud_name' => config('cloudinary.cloud.cloud_name'),
                    'api_key'    => config('cloudinary.cloud.api_key'),
                    'api_secret' => config('cloudinary.cloud.api_secret'),
                ]);

                // Hapus foto Clock In
                try {
                    if ($record->public_id_clock_in) {
                        Cloudinary::destroy($record->public_id_clock_in);
                        Log::info('Berhasil hapus foto Clock In dari Cloudinary: ' . $record->public_id_clock_in);
                    }
                } catch (\Exception $e) {
                    Log::error("Gagal menghapus foto clock_in dari Cloudinary: {$record->public_id_clock_in}, Error: {$e->getMessage()}");
                }

                // Hapus foto Clock Out
                try {
                    if ($record->public_id_clock_out) {
                        Cloudinary::destroy($record->public_id_clock_out);
                        Log::info('Berhasil hapus foto Clock Out dari Cloudinary: ' . $record->public_id_clock_out);
                    }
                } catch (\Exception $e) {
                    Log::error("Gagal menghapus foto clock_out dari Cloudinary: {$record->public_id_clock_out}, Error: {$e->getMessage()}");
                }
            })
            ->after(function () {
                Notification::make()
                    ->title('Presensi & Foto berhasil dihapus dari Cloudinary.')
                    ->success()
                    ->send();
            }),
        ];
    }

}
