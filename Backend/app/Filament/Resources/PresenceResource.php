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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PresenceResource extends Resource
{
    protected static ?string $model = Presence::class;
    protected static ?string $navigationGroup = 'Presensi';
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
                Forms\Components\TextInput::make('clock_in'),
                Forms\Components\TextInput::make('clock_out'),
                Forms\Components\TextInput::make('foto_clock_in')
                    ->maxLength(255),
                Forms\Components\TextInput::make('foto_clock_out')
                    ->maxLength(255),
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Force Delete')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->forceDelete()),
                ])
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
        return static::getModel()::count() >3000 ? 'warning' : 'primary';
    }
}
