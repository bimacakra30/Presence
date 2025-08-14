<?php

namespace App\Filament\Resources\GeoLocatorResource\Pages;

use App\Filament\Resources\GeoLocatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGeoLocators extends ListRecords
{
    protected static string $resource = GeoLocatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
