<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Employee;
use App\Models\Presence;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget\Card;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Card::make('Total Karyawan', Employee::count()),
            Card::make('Total Presensi Hari Ini', Presence::whereDate('created_at', today())->count()),
            Card::make('Proyek Aktif', Project::where('status', 'aktif')->count()),
        ];
    }
}
