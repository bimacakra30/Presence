<?php
namespace App\Filament\Widgets;

use App\Models\Presence;
use Filament\Widgets\ChartWidget;

class PresencePieChart extends ChartWidget
{
    protected static ?string $heading = 'Perbandingan Telat vs Tepat Waktu';

    protected static ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 6;

    protected static ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        // Ambil total telat dan tepat waktu
        $telat = Presence::where('status', 1)->count();
        $tepat = Presence::where('status', 0)->count();

        return [
            'datasets' => [
                [
                    'label' => 'Status Presensi',
                    'data' => [$tepat, $telat],
                    'backgroundColor' => ['#10b981', '#ef4444'], // hijau & merah
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => ['Tepat Waktu', 'Telat'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
