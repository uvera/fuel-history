<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasAnalyticsPeriodFilter;
use App\Models\Car;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class GlobalEfficiencyChart extends ChartWidget
{
    use HasAnalyticsPeriodFilter;

    protected ?string $heading = 'Average efficiency — all cars (L/100km)';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $entriesByCar = Car::query()->with('fuelEntries')->get()->map(fn (Car $car) => $car->fuelEntries);
        $buckets = app(AnalyticsService::class)->bucketsGlobal($entriesByCar, $this->period());

        return [
            'datasets' => [
                [
                    'label' => 'Avg L/100km',
                    'data' => $buckets->map(fn ($bucket) => $bucket->averageL100km())->all(),
                ],
            ],
            'labels' => $buckets->pluck('label')->all(),
        ];
    }
}
