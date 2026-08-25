<?php

namespace App\Services\Data;

use Illuminate\Support\Collection;

/**
 * @property Collection<int, FuelEntryRow> $rows In chronological order.
 * @property Collection<int, FuelStretch> $stretches Closed full-tank-to-full-tank stretches, in chronological order.
 */
final class FuelComputationResult
{
    public function __construct(
        public readonly Collection $rows,
        public readonly Collection $stretches,
    ) {}
}
