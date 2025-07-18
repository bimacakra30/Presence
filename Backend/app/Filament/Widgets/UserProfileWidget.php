<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\Auth;

class UserProfileWidget extends Widget
{
    protected static string $view = 'filament.widgets.user-profile-widget';
    protected static ?int $sort = -1; // Biar di atas

    public function getUser()
    {
        return Auth::user();
    }
}
