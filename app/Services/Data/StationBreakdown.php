<?php

namespace App\Services\Data;

/**
 * Totals for one gas station, across a single car's fuel history.
 */
final class StationBreakdown
{
    public function __construct(
        public readonly string $gasStation,
        public readonly float $totalAmount,
        public readonly float $totalLitres,
        public readonly int $fillUpCount,
    ) {}
}
