<?php
namespace App\Filament\Widgets;

use App\Models\Presence;
use Filament\Widgets\ChartWidget;

class PresencePieChart extends ChartWidget
{
    protected static ?string $heading = 'Perbandingan Telat vs Tepat Waktu';

    protected static ?string $pollingInterval = null;

    // Gunakan columnSpan 6 langsung untuk memastikan bersebelahan
    protected int | string | array $columnSpan = 1;

    protected static ?string $maxHeight = '300px';

    // Tambahkan sort untuk mengatur urutan widget
    protected static ?int $sort = 1;

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
                    'backgroundColor' => ['#0057c9ff', '#ffae00ff'], // hijau & merah
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
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}