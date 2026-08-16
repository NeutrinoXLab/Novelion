<?php

namespace App\Filament\Widgets;

use App\Models\Visit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VisitsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [

            Stat::make(
                'Vizite astăzi',
                Visit::whereDate('created_at', today())->count()
            )
                ->description('Vizite înregistrate astăzi')
                ->descriptionIcon('heroicon-o-eye')
                ->color('info'),

            Stat::make(
                'Vizite ieri',
                Visit::whereDate(
                    'created_at',
                    today()->subDay()
                )->count()
            )
                ->description('Vizite înregistrate ieri')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('gray'),

            Stat::make(
                'Ultimele 7 zile',
                Visit::where(
                    'created_at',
                    '>=',
                    now()->subDays(7)
                )->count()
            )
                ->description('Activitate recentă')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('success'),

            Stat::make(
                'Ultimele 30 zile',
                Visit::where(
                    'created_at',
                    '>=',
                    now()->subDays(30)
                )->count()
            )
                ->description('Activitate din ultima lună')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('warning'),

            Stat::make(
                'Total vizite',
                Visit::count()
            )
                ->description('Toate vizitele înregistrate')
                ->descriptionIcon('heroicon-o-globe-alt')
                ->color('primary'),

        ];
    }
}