<?php

namespace App\Services\Data;

use Illuminate\Support\Collection;

/**
 * All-time fuel summary for a single car. averageL100km is drawn from the
 * same closed-stretch samples AnalyticsBucket::averageL100km() uses per
 * period, just pooled across all periods, so the all-time figure can never
 * disagree with the period-bucketed analytics table.
 */
final class CarFuelSummary
{
    /**
     * @param  Collection<int, StationBreakdown>  $byStation
     * @param  Collection<int, FuelTypeBreakdown>  $byFuelType
     */
    public function __construct(
        public readonly float $totalAmount,
        public readonly float $totalLitres,
        public readonly int $totalKm,
        public readonly ?float $averageL100km,
        public readonly ?float $costPerKm,
        public readonly float $averagePricePerLitre,
        public readonly int $fillUpCount,
        public readonly ?FuelEntryRow $cheapestFillUp,
        public readonly ?FuelEntryRow $priciestFillUp,
        public readonly Collection $byStation,
        public readonly Collection $byFuelType,
    ) {}
}
