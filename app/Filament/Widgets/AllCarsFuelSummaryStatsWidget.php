<?php

namespace App\Filament\Widgets;

use App\Models\Car;
use App\Services\AnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AllCarsFuelSummaryStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $entriesByCar = Car::query()->with('fuelEntries')->get()->map(fn (Car $car) => $car->fuelEntries);
        $summary = app(AnalyticsService::class)->allTimeSummaryGlobal($entriesByCar);

        return [
            Stat::make('Total spend (RSD)', self::fmt($summary->totalAmount, 2)),
            Stat::make('Total litres', self::fmt($summary->totalLitres, 3)),
            Stat::make('Total distance (km)', self::fmt($summary->totalKm)),
            Stat::make('Avg L/100km', $summary->averageL100km === null ? '—' : self::fmt($summary->averageL100km, 2)),
            Stat::make('Cost per km (RSD)', $summary->costPerKm === null ? '—' : self::fmt($summary->costPerKm, 2)),
            Stat::make('Avg price/L (RSD)', self::fmt($summary->averagePricePerLitre, 2)),
            Stat::make('Fill-ups', (string) $summary->fillUpCount),
            Stat::make(
                'Cheapest fill-up',
                $summary->cheapestFillUp === null ? '—' : self::fmt($summary->cheapestFillUp->pricePerLitre, 2).' RSD/L',
            )->description($summary->cheapestFillUp === null ? null : sprintf(
                '%s — %s',
                $summary->cheapestFillUp->entry->date_of_filling->format('Y-m-d'),
                $summary->cheapestFillUp->entry->gas_station,
            )),
            Stat::make(
                'Priciest fill-up',
                $summary->priciestFillUp === null ? '—' : self::fmt($summary->priciestFillUp->pricePerLitre, 2).' RSD/L',
            )->description($summary->priciestFillUp === null ? null : sprintf(
                '%s — %s',
                $summary->priciestFillUp->entry->date_of_filling->format('Y-m-d'),
                $summary->priciestFillUp->entry->gas_station,
            )),
        ];
    }

    /**
     * Serbian numeric convention: dot for thousands, comma for decimals.
     */
    private static function fmt(float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, ',', '.');
    }
}
