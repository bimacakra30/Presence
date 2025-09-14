<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermitResource\Pages;
use App\Filament\Resources\PermitResource\RelationManagers;
use App\Models\Permit;
use App\Services\FirestoreService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;


class PermitResource extends Resource
{
    protected static ?string $model = Permit::class;
    protected static ?string $navigationGroup = 'Manajemen Karyawan & Perizinan';
    protected static ?string $navigationLabel = 'Perizinan';
    protected static ?string $navigationBadgeTooltip = 'Jumlah Perizinan Karyawan';
    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama_karyawan')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('jenis_perizinan')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('tanggal_mulai')
                    ->required(),
                Forms\Components\DatePicker::make('tanggal_selesai'),
                Forms\Components\TextInput::make('deskripsi')
                    ->maxLength(255),
                Forms\Components\TextInput::make('bukti_izin')
                    ->maxLength(255),
                Forms\Components\TextInput::make('status')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_karyawan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jenis_perizinan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tanggal_mulai')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tanggal_selesai')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deskripsi')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('bukti_izin')
                    ->label('Bukti Perizinan')
                    ->height(200)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'warning' => 'pending'
                    ])
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Delete')
                        ->requiresConfirmation()
                        ->modalHeading('Confirm Deletion')
                        ->modalDescription('This will permanently delete selected presence records, associated photos in Cloudinary, and related data in Firestore. Are you sure?')
                        ->action(fn ($records) => $records->each->delete())
                        ->successNotificationMessage('Presences and associated photos deleted successfully.'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermits::route('/'),
            'create' => Pages\CreatePermit::route('/create'),
        ];
    }

        public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::count() > 3000 ? 'warning' : 'primary';
    }
}
