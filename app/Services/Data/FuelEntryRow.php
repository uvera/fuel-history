<?php

namespace App\Services\Data;

use App\Models\FuelEntry;

/**
 * One row of a car's fuel-entry table, with the server-computed
 * comparison columns attached.
 */
final class FuelEntryRow
{
    public function __construct(
        public readonly FuelEntry $entry,
        public readonly float $pricePerLitre,
        public readonly ?int $kmSincePrevious,
        public readonly ?float $l100kmAccurate,
        public readonly ?float $l100kmTripEstimate,
    ) {}
}
