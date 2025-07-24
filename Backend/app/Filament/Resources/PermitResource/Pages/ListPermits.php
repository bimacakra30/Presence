<?php

namespace App\Filament\Resources\PermitResource\Pages;

use App\Filament\Resources\PermitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\PresenceChart;

class ListPermits extends ListRecords
{
    protected static string $resource = PermitResource::class;

    protected static ?string $title = 'Perizinan';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
