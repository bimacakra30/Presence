<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeePositionResource\Pages;
use App\Filament\Resources\EmployeePositionResource\RelationManagers;
use App\Models\EmployeePosition;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeePositionResource extends Resource
{
    protected static ?string $model = EmployeePosition::class;
    protected static ?string $navigationGroup = 'Management';
    protected static ?string $navigationLabel = 'Employee Positions Management';
    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'name') // jika relasi sudah ada
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('position')
                    ->label('Position')
                    ->options([
                        'Manager' => 'Manager',
                        'Staff' => 'Staff',
                        'Intern' => 'Intern',
                    ])
                    ->default('Staff')
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->required(),
                Forms\Components\DatePicker::make('end_date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Employee Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('position')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeePositions::route('/'),
            'create' => Pages\CreateEmployeePosition::route('/create'),
            'edit' => Pages\EditEmployeePosition::route('/{record}/edit'),
        ];
    }
}
