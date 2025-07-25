<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class AppLabel extends Widget
{
    protected static string $view = 'filament.widgets.app-label';

    protected int | string | array $columnSpan = 1;
}

