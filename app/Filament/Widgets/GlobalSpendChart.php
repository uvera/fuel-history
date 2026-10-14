<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasAnalyticsPeriodFilter;
use App\Models\Car;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class GlobalSpendChart extends ChartWidget
{
    use HasAnalyticsPeriodFilter;

    protected ?string $heading = 'Spend — all cars (RSD)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $entriesByCar = Car::query()->with('fuelEntries.car')->get()->map(fn (Car $car) => $car->fuelEntries);
        $buckets = app(AnalyticsService::class)->bucketsGlobal($entriesByCar, $this->period());

        return [
            'datasets' => [
                [
                    'label' => 'Spend (RSD)',
                    'data' => $buckets->pluck('totalAmount')->all(),
                ],
            ],
            'labels' => $buckets->pluck('label')->all(),
        ];
    }
}
