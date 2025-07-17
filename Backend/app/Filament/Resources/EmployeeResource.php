<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $navigationGroup = 'Management';
    protected static ?string $navigationBadgeTooltip = 'Jumlah Karyawan';
    protected static ?string $navigationLabel = 'Karyawan';
    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('photo')
                    ->label('Photo Profile')
                    ->directory('photos')
                    ->image()
                    ->imagePreviewHeight('200')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->visibility('public')
                    ->previewable()
                    ->nullable(),
                Forms\Components\TextInput::make('name')
                    ->label('Full Name')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->required(),

                Forms\Components\TextInput::make('username')
                    ->label('Username')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->hidden(fn ($context) => $context === 'edit')
                    ->password()
                    ->required()
                    ->minLength(8),
                Forms\Components\TextInput::make('phone')
                    ->label('Phone Number')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->tel()
                    ->maxLength(20)
                    ->nullable(),

                Forms\Components\Textarea::make('address')
                    ->label('Address')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->columnSpanFull()
                    ->nullable(),

                Forms\Components\DatePicker::make('date_of_birth')
                    ->label('Date of Birth')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->nullable(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options([
                        'aktif' => 'Active',
                        'non-aktif' => 'Inactive',
                        'terminated' => 'Terminated',
                    ])
                    ->default('aktif'),

                Forms\Components\Select::make('provider')
                    ->label('Login Provider')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->options([
                        'google' => 'Google',
                        'facebook' => 'Facebook',
                        'twitter' => 'Twitter',
                        'github' => 'GitHub',
                    ])
                    ->default('google')
                    ->required()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Photo')
                    ->getStateUsing(fn ($record) => $record->photo ? asset('storage/' . $record->photo) : null)
                    ->circular()
                    ->height(50),
                Tables\Columns\TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_of_birth')
                    ->label('Birth Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\ViewColumn::make('provider')
                    ->label('Provider')
                    ->view('filament.components.provider-icon'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'aktif' => 'success',
                        'non-aktif' => 'warning',
                        'terminated' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
