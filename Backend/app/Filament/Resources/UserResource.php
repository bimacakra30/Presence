<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\UserResource\Pages\CreateUser;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                 FileUpload::make('photo')
                    ->label('Foto Profile')
                    ->avatar()
                    ->directory('profile-images')
                    ->image()
                    ->imagePreviewHeight('100')
                    ->previewable()
                    ->nullable(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->maxLength(255)
                    ->visible(fn ($livewire) => $livewire instanceof CreateUser)
                    ->label('Password')
                    ->dehydrated(fn ($state) => filled($state)) // hanya kirim ke model jika diisi
                    ->required(fn ($livewire) => $livewire instanceof \App\Filament\Resources\UserResource\Pages\CreateUser)
                    ->hint(fn ($livewire) => $livewire instanceof \App\Filament\Resources\UserResource\Pages\EditUser
                        ? 'Kosongkan jika tidak ingin mengubah password'
                        : null),
                Forms\Components\Select::make('role')
                    ->options([
                        'superadmin' => 'Super Admin',
                        'admin' => 'Admin',
                    ])
                    ->default('superadmin')
                    ->required()
                    ->label('Role'),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active')
                    ->required()
                    ->label('Status'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto Profile')
                    ->getStateUsing(fn($record) => asset('storage/' . $record->photo))
                    ->circular()
                    ->size(80),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->colors([
                        'warning' => 'admin',
                        'primary' => 'superadmin',
                    ])
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->role === 'superadmin';
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
