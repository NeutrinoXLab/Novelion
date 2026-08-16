<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [

            Stat::make(
                'Comenzi în așteptare',
                Order::where('status', 'pending')->count()
            )
                ->description('Necesită procesare')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),

            Stat::make(
                'În procesare',
                Order::where('status', 'processing')->count()
            )
                ->description('Se pregătesc pentru expediere')
                ->descriptionIcon('heroicon-o-truck')
                ->color('info'),

            Stat::make(
                'Stoc redus',
                Product::whereColumn(
                    'stock_quantity',
                    '<=',
                    'low_stock_threshold'
                )->count()
            )
                ->description('Produse care trebuie reaprovizionate')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make(
                'Vânzări luna aceasta',
                number_format(
                    Order::whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->sum('total'),
                    2,
                    ',',
                    '.'
                ) . ' RON'
            )
                ->description('Total vânzări luna curentă')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),

        ];
    }
}