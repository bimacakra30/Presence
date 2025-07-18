<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\PresenceChart;
use App\Filament\Widgets\UserProfileWidget;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static string $view = 'filament.pages.dashboard';

    // Tambahkan ini untuk menampilkan widget
    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
            UserProfileWidget::class,
            PresenceChart::class,
        ];
    }
}

