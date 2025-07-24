<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\PresenceChart;
use App\Filament\Widgets\UserProfileWidget;
use App\Models\Presence;
use App\Filament\Widgets\PresencePieChart;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static string $view = 'filament.pages.dashboard';

    // Tambahkan ini untuk menampilkan widget
    protected function getHeaderWidgets(): array
    {
        return [
            UserProfileWidget::class,
            StatsOverview::class,
            PresencePieChart::class,
            PresenceChart::class,
        ];
    }
    public function getTitle(): string
    {
        return 'Dashboard Admin';
    }  
}

