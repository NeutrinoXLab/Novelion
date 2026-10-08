<?php

namespace App\Filament\Widgets;

use App\Services\VisitorAnalytics;
use Filament\Widgets\ChartWidget;

class VisitsChart extends ChartWidget
{
    protected ?string $heading = 'Vizite în ultimele 30 de zile';

    protected function getData(): array
    {
        $analytics = app(VisitorAnalytics::class);
        $startDate = $analytics->day()->subDays(29);
        $visits = $analytics->summary()['days'];

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
                    'label' => 'Vizite unice IP/zi (istoric vechi exclus)',
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
