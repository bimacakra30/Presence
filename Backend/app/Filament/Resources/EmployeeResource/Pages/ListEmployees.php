<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\UserProfileWidget;
use App\Filament\Widgets\PresenceChart;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string
    {
        return 'Karyawan';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
        ];
    }
}
