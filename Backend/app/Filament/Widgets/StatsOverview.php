<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget\Card;
use App\Models\Employee;
use App\Models\Presence;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends \Filament\Widgets\StatsOverviewWidget
{
    protected function getStats(): array
    {
        $cards = [];

        // Ambil user yang sedang login
        $user = Auth::user();

        // Presensi hari ini dan kemarin
        $todayCount = Presence::whereDate('created_at', today())->count();
        $yesterdayCount = Presence::whereDate('created_at', today()->subDay())->count();

        // Hitung tren
        $trend = $todayCount - $yesterdayCount;
        $isUp = $trend >= 0;
        $trendText = ($isUp ? '+' : '') . $trend . ' dari kemarin';
        $trendIcon = $isUp ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $trendColor = $isUp ? 'success' : 'danger';

        // Ambil data 7 hari terakhir
        $last7Days = collect(range(6, 0))->map(fn ($i) => Carbon::now()->subDays($i)->format('Y-m-d'));

        $presenceCounts = Presence::selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->whereDate('created_at', '>=', now()->subDays(6))
            ->groupBy('date')
            ->pluck('total', 'date');

        $chartData = $last7Days->map(fn ($date) => $presenceCounts[$date] ?? 0)->toArray();

        // 👑 Card untuk Super Admin saja
        if ($user && $user->role === 'superadmin') {
            $cards[] = Card::make('Total Users', User::count())
                ->description('Semua pengguna terdaftar')
                ->descriptionIcon('heroicon-m-users')
                ->icon('heroicon-o-users')
                ->color('primary')
                ->url(route('filament.admin.resources.users.index'));
        }

        // Card lain (semua role bisa lihat)
        $cards[] = Card::make('Total Karyawan', Employee::count())
            ->description('Data semua karyawan')
            ->descriptionIcon('heroicon-m-identification')
            ->icon('heroicon-o-briefcase')
            ->color('warning')
            ->url(route('filament.admin.resources.employees.index'));

        $cards[] = Card::make('Presensi Hari Ini', $todayCount)
            ->description($trendText)
            ->descriptionIcon($trendIcon)
            ->icon('heroicon-o-check-circle')
            ->color($trendColor)
            ->chart($chartData)
            ->url(route('filament.admin.resources.presences.index'));

        $cards[] = Card::make('Proyek Aktif', Project::where('status', 'aktif')->count())
            ->description('Proyek yang masih berjalan')
            ->descriptionIcon('heroicon-m-folder-open')
            ->icon('heroicon-o-folder')
            ->color('info')
            ->url(route('filament.admin.resources.projects.index'));

        return $cards;
    }
}

