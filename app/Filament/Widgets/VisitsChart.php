<?php

namespace App\Filament\Widgets;

use App\Models\Visit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class VisitsChart extends ChartWidget
{
    protected ?string $heading = 'Vizite în ultimele 30 de zile';

    protected function getData(): array
    {
        $startDate = now()->subDays(29)->startOfDay();

        $visits = Visit::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 30; $i++) {

            $date = $startDate->copy()->addDays($i);

            $key = $date->format('Y-m-d');

            $labels[] = $date->format('d.m');

            $data[] = $visits[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Vizite',
                    'data' => $data,
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