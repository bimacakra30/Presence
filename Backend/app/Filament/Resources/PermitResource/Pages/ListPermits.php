<?php

namespace App\Filament\Resources\PermitResource\Pages;

use App\Filament\Resources\PermitResource;
use App\Filament\Widgets\StatsOverview;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Filament\Actions\Action;
use App\Services\FirestoreService;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\DeleteAction;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Filament\Tables;
use Illuminate\Support\Facades\Log;
use App\Models\Permit;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

class ListPermits extends ListRecords
{
    protected static string $resource = PermitResource::class;

    public function getTitle(): string
    {
        return 'Perizinan Karyawan';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Muat Data Perizinan')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->requiresConfirmation('Apakah Anda yakin ingin memuat data perizinan?')
                ->modalHeading('Muat Ulang Data Perizinan?')
                ->modalSubheading('Ini akan menghapus data perizinan yang ada dan menggantinya dengan data terbaru.')
                ->modalButton('Muat Ulang')
                ->action(function () {
                    $service = new FirestoreService();
                    $lastDocument = null;
                    $allPerizinan = [];

                    do {
                        $result = $service->getPerizinan(100, $lastDocument);
                        $perizinanData = $result['data'];
                        $lastDocument = $result['lastDocument'];

                        foreach ($perizinanData as $perizinan) {
                            Permit::withoutEvents(function () use ($perizinan) {
                                Permit::updateOrCreate(
                                    [
                                        'firestore_id' => $perizinan['firestore_id'] ?? null,
                                        'uid' => $perizinan['uid'] ?? '',
                                        'tanggal_masuk' => Carbon::parse($perizinan['submissionDate'] ?? now())->toDateString(),
                                    ],
                                    [
                                        'nama_karyawan' => $perizinan['employeeName'] ?? '',
                                        'jenis_perizinan' => $perizinan['permitType'] ?? '',
                                        'tanggal_mulai' => isset($perizinan['startDate']) ? Carbon::parse($perizinan['startDate']) : null,
                                        'tanggal_selesai' => isset($perizinan['endDate']) ? Carbon::parse($perizinan['endDate']) : null,
                                        'deskripsi' => $perizinan['description'] ?? '',
                                        'bukti_izin' => $perizinan['proofImageUrl'] ?? null,
                                        'bukti_izin_public_id' => $perizinan['proofImagePublicId'] ?? null,
                                        'status' => $perizinan['status'] ?? true,
                                    ]
                                );
                            });
                        }

                        $allPerizinan = array_merge($allPerizinan, $perizinanData);
                    } while ($lastDocument && count($perizinanData) > 0);

                    Notification::make()
                        ->title('Data Perizinan berhasil disinkronkan!')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getTableActions(): array
{
    return [
        Tables\Actions\Action::make('terima')
            ->label('Terima')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Permit $record) => $record->status === 'pending') // Hanya muncul jika status pending
            ->requiresConfirmation()
            ->modalHeading('Terima Perizinan')
            ->modalSubheading('Apakah Anda yakin ingin menerima perizinan ini?')
            ->modalButton('Ya, Terima')
            ->action(function (Permit $record) {
                try {
                    $record->update(['status' => 'approved']);
                    Notification::make()
                        ->title('Perizinan berhasil diterima.')
                        ->success()
                        ->send();

                    // Opsional: Sinkronkan ke Firestore
                    $service = new FirestoreService();
                    $service->updatePerizinan($record->firestore_id, ['status' => 'approved']);
                } catch (\Exception $e) {
                    Log::error("Gagal menerima perizinan ID: {$record->id}, Error: {$e->getMessage()}");
                    Notification::make()
                        ->title('Gagal Menerima Perizinan')
                        ->body("Terjadi kesalahan: {$e->getMessage()}")
                        ->danger()
                        ->send();
                }
            }),
        Tables\Actions\Action::make('tolak')
            ->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Permit $record) => $record->status === 'pending') // Hanya muncul jika status pending
            ->requiresConfirmation()
            ->modalHeading('Tolak Perizinan')
            ->modalSubheading('Apakah Anda yakin ingin menolak perizinan ini?')
            ->modalButton('Ya, Tolak')
            ->action(function (Permit $record) {
                try {
                    $record->update(['status' => 'rejected']);
                    Notification::make()
                        ->title('Perizinan berhasil ditolak.')
                        ->success()
                        ->send();

                    // Opsional: Sinkronkan ke Firestore
                    $service = new FirestoreService();
                    $service->updatePerizinan($record->firestore_id, ['status' => 'rejected']);
                } catch (\Exception $e) {
                    Log::error("Gagal menolak perizinan ID: {$record->id}, Error: {$e->getMessage()}");
                    Notification::make()
                        ->title('Gagal Menolak Perizinan')
                        ->body("Terjadi kesalahan: {$e->getMessage()}")
                        ->danger()
                        ->send();
                }
            }),
        DeleteAction::make()
            ->before(function (Permit $record) {
                Log::debug('Starting DeleteAction for Permit ID: ' . $record->id);
                // Cloudinary configuration (if needed, or configure globally)
                Configuration::instance([
                    'cloud' => [
                        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                        'api_key' => env('CLOUDINARY_API_KEY'),
                        'api_secret' => env('CLOUDINARY_API_SECRET'),
                    ],
                    'url' => [
                        'secure' => true,
                    ],
                ]);
            })
            ->action(function (Permit $record) {
                try {
                    $record->delete(); // This triggers the model's deleted event
                    Notification::make()
                        ->title('Perizinan & Foto berhasil dihapus.')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Log::error("Gagal menghapus Permit ID: {$record->id}, Error: {$e->getMessage()}");
                    Notification::make()
                        ->title('Gagal Menghapus Perizinan')
                        ->body("Gagal menghapus perizinan: {$e->getMessage()}")
                        ->danger()
                        ->send();
                }
            }),
    ];
}

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
        ];
    }
}