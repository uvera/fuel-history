<?php

namespace App\Services;

use App\Models\FuelEntry;
use App\Services\Data\FuelComputationResult;
use App\Services\Data\FuelEntryRow;
use App\Services\Data\FuelStretch;
use Illuminate\Support\Collection;

/**
 * Computes the virtual/comparison columns for a single car's fuel
 * entries — a pure, DB-free forward pass over chronologically ordered
 * entries. This is the single source of truth for "what counts as a
 * closed full-tank stretch", shared between the per-car table view and
 * the analytics bucketing service, so the two can never disagree.
 *
 * Deliberately not implemented as SQL (window functions/CTEs): at this
 * app's data volume (hundreds of rows per car, not millions) a plain
 * PHP pass over an ordered collection is far easier to test exhaustively
 * and reason about than an equivalent multi-CTE SQL query.
 */
class FuelStretchService
{
    /**
     * @param  Collection<int, FuelEntry>  $entries  All of one car's entries, any order — sorted internally by date_of_filling then id.
     */
    public function compute(Collection $entries): FuelComputationResult
    {
        $ordered = $entries
            ->sortBy([
                ['date_of_filling', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $rows = [];
        $stretches = [];

        $previousOdometer = null;
        $previousFullFillOdometer = null;
        $stretchLitres = 0.0;

        foreach ($ordered as $entry) {
            $litres = (float) $entry->litres_filled;
            $pricePerLitre = $litres > 0 ? (float) $entry->amount / $litres : 0.0;

            $kmSincePrevious = ($previousOdometer !== null && $entry->odometer_at_fill !== null)
                ? $entry->odometer_at_fill - $previousOdometer
                : null;

            $stretchLitres += $litres;

            $l100kmAccurate = null;
            $l100kmTripEstimate = null;

            if ($entry->is_partial_fill) {
                $tripKm = $entry->trip_km !== null ? (float) $entry->trip_km : null;
                if ($tripKm !== null && $tripKm > 0) {
                    $l100kmTripEstimate = ($litres / $tripKm) * 100;
                }
            } else {
                if ($previousFullFillOdometer !== null && $entry->odometer_at_fill !== null) {
                    $span = $entry->odometer_at_fill - $previousFullFillOdometer;
                    if ($span > 0) {
                        $l100kmAccurate = ($stretchLitres / $span) * 100;
                        $stretches[] = new FuelStretch($entry, $stretchLitres, $span, $l100kmAccurate);
                    }
                }

                $previousFullFillOdometer = $entry->odometer_at_fill;
                $stretchLitres = 0.0;
            }

            $rows[] = new FuelEntryRow($entry, $pricePerLitre, $kmSincePrevious, $l100kmAccurate, $l100kmTripEstimate);
            $previousOdometer = $entry->odometer_at_fill;
        }

        return new FuelComputationResult(collect($rows), collect($stretches));
    }
}
