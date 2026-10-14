<?php

namespace App\Services\Data;

/**
 * What happened when one fuel station was merged into another.
 * Variants that only existed on the removed station are kept.
 * Variants with the same name are combined onto the station that remains.
 */
final class FuelStationMergeResult
{
    public function __construct(
        public readonly int $entriesMoved,
        public readonly int $variantsMoved,
        public readonly int $variantsCombined,
    ) {}
}
