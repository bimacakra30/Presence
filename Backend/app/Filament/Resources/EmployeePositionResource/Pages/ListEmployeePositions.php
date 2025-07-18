<?php

namespace App\Filament\Resources\EmployeePositionResource\Pages;

use App\Filament\Resources\EmployeePositionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePositions extends ListRecords
{
    protected static string $resource = EmployeePositionResource::class;

    public function getTitle(): string
    {
        return 'Jabatan Karyawan';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
