<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasAnalyticsPeriodFilter;
use App\Models\Car;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class GlobalVolumeChart extends ChartWidget
{
    use HasAnalyticsPeriodFilter;

    protected ?string $heading = 'Volume — all cars (litres)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $entriesByCar = Car::query()->with('fuelEntries')->get()->map(fn (Car $car) => $car->fuelEntries);
        $buckets = app(AnalyticsService::class)->bucketsGlobal($entriesByCar, $this->period());

        return [
            'datasets' => [
                [
                    'label' => 'Litres',
                    'data' => $buckets->pluck('totalLitres')->all(),
                ],
            ],
            'labels' => $buckets->pluck('label')->all(),
        ];
    }
}
