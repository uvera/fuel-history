<?php

namespace App\Services\Data;

use App\Enums\FuelType;

/**
 * Totals for one car fuel type plus fuel variant. Fuel type comes from
 * the car, so a single car splits by variant only — a grade change still
 * shows up as its own row.
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
