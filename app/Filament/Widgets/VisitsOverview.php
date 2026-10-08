<?php

namespace App\Filament\Widgets;

use App\Models\Visit;
use App\Services\VisitorAnalytics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VisitsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $counts = app(VisitorAnalytics::class)->summary();

        return [

            Stat::make(
                'Vizite astăzi',
                $counts['today']
            )
                ->description('IP-uri unice astăzi · București')
                ->descriptionIcon('heroicon-o-eye')
                ->color('info'),

            Stat::make(
                'Vizite ieri',
                $counts['yesterday']
            )
                ->description('IP-uri unice ieri · București')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('gray'),

            Stat::make(
                'Ultimele 7 zile',
                $counts['seven']
            )
                ->description('Suma vizitelor unice zilnice')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('success'),

            Stat::make(
                'Ultimele 30 zile',
                $counts['thirty']
            )
                ->description('Suma vizitelor unice zilnice')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('warning'),

            Stat::make(
                'Total vizite',
                $counts['total']
            )
                ->description('IP/zi; istoric vechi exclus: '.Visit::count().' sesiuni')
                ->descriptionIcon('heroicon-o-globe-alt')
                ->color('primary'),

        ];
    }
}
