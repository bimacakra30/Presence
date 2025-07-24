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
use App\Exports\PresenceExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\DatePicker;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\PresencePieChart;
use App\Filament\Widgets\PresenceChart;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

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
                                'tanggal' => Carbon::parse($absen['date'] ?? now())->toDateString(),
                            ],
                            [
                                'nama' => $absen['name'] ?? '',
                                'clock_in' => isset($absen['clockIn']) ? Carbon::parse($absen['clockIn']) : null,
                                'public_id_clock_in' => $absen['fotoClockInPublicId'] ?? null,
                                'public_id_clock_out' => $absen['fotoClockOutPublicId'] ?? null,
                                'clock_out' => isset($absen['clockOut']) ? Carbon::parse($absen['clockOut']) : null,
                                'foto_clock_in' => $absen['fotoClockIn'] ?? null,
                                'foto_clock_out' => $absen['fotoClockOut'] ?? null,
                                'status' => $absen['late'] ?? true,
                                'durasi_keterlambatan' => $absen['lateDuration'] ?? null,
                            ]
                        );
                    }
                    Notification::make()
                        ->title('Data presensi berhasil disinkronkan!')
                        ->success()
                        ->send();
                }),
            Action::make('export_excel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    DatePicker::make('tanggal_dari')
                        ->label('Dari Tanggal')
                        ->default(now()->subDays(1)->toDateString()) // Default: kemarin
                        ->displayFormat('Y-m-d'),
                    DatePicker::make('tanggal_sampai')
                        ->label('Sampai Tanggal')
                        ->default(now()->toDateString()) // Default: hari ini
                        ->displayFormat('Y-m-d'),
                ])
                ->action(function (array $data) {
                    $tanggal_dari = $data['tanggal_dari'] ?? null;
                    $tanggal_sampai = $data['tanggal_sampai'] ?? null;
                    return Excel::download(
                        new PresenceExport($tanggal_dari, $tanggal_sampai),
                        'Presensi_Karyawan_' . now()->format('m_His') . '.xlsx'
                    );
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            DeleteAction::make()
            ->before(function (Presence $record) {
                // Konfigurasi Cloudinary manual (sesuai env)
                Configuration::instance([
                    'cloud' => [
                        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                        'api_key'    => env('CLOUDINARY_API_KEY'),
                        'api_secret' => env('CLOUDINARY_API_SECRET'),
                    ],
                    'url' => [
                        'secure' => true,
                    ],
                ]);
                try {
                    if ($record->public_id_clock_in) {
                        (new UploadApi())->destroy($record->public_id_clock_in);
                        Log::info('Berhasil hapus foto Clock In dari Cloudinary: ' . $record->public_id_clock_in);
                    }
                } catch (\Exception $e) {
                    Log::error("Gagal menghapus foto clock_in dari Cloudinary: {$record->public_id_clock_in}, Error: {$e->getMessage()}");
                }

                try {
                    if ($record->public_id_clock_out) {
                        (new UploadApi())->destroy($record->public_id_clock_out);
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
            })
        ];
    }


    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
            PresencePieChart::class,
            PresenceChart::class,
        ];
    }
}