<?php

namespace App\Filament\Widgets;

use App\Models\Presence;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PresenceChart extends ChartWidget
{
    protected static ?string $heading = 'Grafik Presensi';

    // Tambahkan filter dropdown
    protected static ?string $pollingInterval = null;

    protected function getFilters(): ?array
    {
        return [
            'daily' => 'Harian',
            'weekly' => 'Mingguan',
            'monthly' => 'Bulanan',
        ];
    }

    protected function getData(): array
    {
        $filter = $this->filter ?? 'daily';

        if ($filter === 'weekly') {
            return $this->getWeeklyData();
        } elseif ($filter === 'monthly') {
            return $this->getMonthlyData();
        }

        return $this->getDailyData();
    }

    protected function getDailyData(): array
    {
        $data = Presence::selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = collect(range(0, 29))->map(function ($i) {
            return Carbon::now()->subDays(29 - $i)->format('Y-m-d');
        });

        $dataset = $labels->map(fn($date) =>
            $data->firstWhere('date', $date)->total ?? 0
        );

        return [
            'datasets' => [
                [
                    'label' => 'Presensi Harian',
                    'data' => $dataset,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => '#93c5fd',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getWeeklyData(): array
    {
        $data = Presence::selectRaw('YEARWEEK(created_at, 1) as week, COUNT(*) as total')
            ->where('created_at', '>=', now()->subWeeks(12))
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        $labels = collect(range(0, 11))->map(function ($i) {
            return 'Minggu ke-' . (12 - $i);
        })->reverse()->values();

        $startWeeks = collect(range(0, 11))->map(function ($i) {
            return Carbon::now()->startOfWeek()->subWeeks(11 - $i)->format('oW');
        });

        $dataset = $startWeeks->map(fn($week) =>
            $data->firstWhere('week', $week)->total ?? 0
        );

        return [
            'datasets' => [
                [
                    'label' => 'Presensi Mingguan',
                    'data' => $dataset,
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#6ee7b7',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getMonthlyData(): array
    {
        $data = Presence::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as total')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = collect(range(0, 11))->map(function ($i) {
            return Carbon::now()->subMonths(11 - $i)->format('Y-m');
        });

        $dataset = $labels->map(fn($month) =>
            $data->firstWhere('month', $month)->total ?? 0
        );

        return [
            'datasets' => [
                [
                    'label' => 'Presensi Bulanan',
                    'data' => $dataset,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => '#fcd34d',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
