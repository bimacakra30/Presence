<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PresenceResource\Pages;
use App\Filament\Resources\PresenceResource\RelationManagers;
use App\Models\Presence;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;


class PresenceResource extends Resource
{
    protected static ?string $model = Presence::class;
    protected static ?string $navigationGroup = 'Presensi & Perizinan';
    protected static ?string $navigationBadgeTooltip = 'Jumlah Presensi Karyawan';
    protected static ?string $navigationLabel = 'Presensi Karyawan';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('uid')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('tanggal')
                    ->required(),
                Forms\Components\TimePicker::make('clock_in'),
                Forms\Components\TimePicker::make('clock_out'),
                Forms\Components\FileUpload::make('foto_clock_in')
                    ->image()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            try {
                                $uploaded = Cloudinary::upload($state->getRealPath(), [
                                    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
                                ]);
                                $set('foto_clock_in', $uploaded->getSecurePath());
                                $set('public_id_clock_in', $uploaded->getPublicId());
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Upload Failed')
                                    ->body('Failed to upload clock-in photo: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }
                    }),
                Forms\Components\FileUpload::make('foto_clock_out')
                    ->image()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            try {
                                $uploaded = Cloudinary::upload($state->getRealPath(), [
                                    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
                                ]);
                                $set('foto_clock_out', $uploaded->getSecurePath());
                                $set('public_id_clock_out', $uploaded->getPublicId());
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Upload Failed')
                                    ->body('Failed to upload clock-out photo: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tanggal')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('clock_in')
                    ->label('Jam Masuk')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('foto_clock_in')
                    ->label('Dokumentasi Kehadiran')
                    ->height(200)
                    ->searchable(),
                Tables\Columns\TextColumn::make('clock_out')
                    ->label('Jam Pulang'),
                Tables\Columns\ImageColumn::make('foto_clock_out')
                    ->label('Dokumentasi Pulang')
                    ->height(200),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Terlambat' : 'Tidak')
                    ->color(fn (bool $state) => $state ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('durasi_keterlambatan')
                    ->label('Durasi Keterlambatan')
                    ->sortable(),
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
                Filter::make('tanggal_range')
                    ->form([
                        DatePicker::make('tanggal_dari')
                            ->label('Dari Tanggal'),
                        DatePicker::make('tanggal_sampai')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['tanggal_dari'], fn ($query) => $query->whereDate('tanggal', '>=', $data['tanggal_dari']))
                            ->when($data['tanggal_sampai'], fn ($query) => $query->whereDate('tanggal', '<=', $data['tanggal_sampai']));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = '';
                        if ($data['tanggal_dari']) {
                            $indicator .= 'Dari: ' . $data['tanggal_dari'];
                        }
                        if ($data['tanggal_sampai']) {
                            $indicator .= ($indicator ? ' - ' : '') . 'Sampai: ' . $data['tanggal_sampai'];
                        }
                        return $indicator ?: null;
                    }),
            ])
            ->actions([
                //
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
            'index' => Pages\ListPresences::route('/'),
            'create' => Pages\CreatePresence::route('/create'),
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