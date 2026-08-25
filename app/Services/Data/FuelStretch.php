<?php

namespace App\Services\Data;

use App\Models\FuelEntry;

/**
 * A closed "full tank to full tank" stretch: the litres consumed (the
 * closing full fill plus any partial fills since the previous full fill)
 * divided by the distance covered since that previous full fill. Used by
 * both the per-car fuel-entry table (as the "accurate" L/100km column) and
 * analytics (period-bucketed average efficiency) — see FuelStretchService.
 */
final class FuelStretch
{
    public function __construct(
        public readonly FuelEntry $closingEntry,
        public readonly float $totalLitres,
        public readonly int $odometerSpan,
        public readonly float $l100km,
    ) {}
}
