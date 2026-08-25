<?php

namespace App\Services\Data;

use App\Enums\FuelType;

/**
 * Totals for one fuel_type/fuel_variant combination, across a single car's
 * fuel history — lets a dual-fuel car (e.g. petrol + LPG) or a car that
 * changed octane grade see spend/efficiency broken out per combination.
 */
final class FuelTypeBreakdown
{
    public function __construct(
        public readonly ?FuelType $fuelType,
        public readonly ?string $fuelVariant,
        public readonly float $totalAmount,
        public readonly float $totalLitres,
        public readonly ?float $averageL100km,
        public readonly int $fillUpCount,
    ) {}

    public function label(): string
    {
        return trim(($this->fuelType?->getLabel() ?? 'Unknown').($this->fuelVariant !== null ? " ({$this->fuelVariant})" : ''));
    }
}
