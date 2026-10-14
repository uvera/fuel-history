<?php

namespace App\Services;

use App\Models\FuelEntry;
use App\Services\Data\AnalyticsBucket;
use App\Services\Data\CarFuelSummary;
use App\Services\Data\FuelEntryRow;
use App\Services\Data\FuelStretch;
use App\Services\Data\FuelTypeBreakdown;
use App\Services\Data\StationBreakdown;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Period-buckets spend/volume/efficiency, reusing FuelStretchService as
 * the single source of truth for "what counts as a closed full-tank
 * stretch" — the same abstraction the fuel-entry table uses, so the two
 * views can never disagree.
 */
class AnalyticsService
{
    public function __construct(
        private readonly FuelStretchService $stretchService,
    ) {}

    /**
     * @param  Collection<int, FuelEntry>  $entries  All of ONE car's entries.
     * @return Collection<int, AnalyticsBucket> Chronological.
     */
    public function bucketsForCar(Collection $entries, AnalyticsPeriod $period): Collection
    {
        $result = $this->stretchService->compute($entries);

        return $this->bucketize(
            $result->rows->map(fn ($row) => $row->entry),
            $result->stretches,
            $period,
        );
    }

    /**
     * Sums per-car bucketed results together. Deliberately never merges
     * multiple cars' entries into one chronological stream before
     * computing stretches — that would corrupt stretch grouping across
     * unrelated vehicles (a fill-up on car A immediately "closing" a
     * stretch that started on car B).
     *
     * @param  Collection<int, Collection<int, FuelEntry>>  $entriesByCar  One entry collection per car.
     * @return Collection<int, AnalyticsBucket> Chronological.
     */
    public function bucketsGlobal(Collection $entriesByCar, AnalyticsPeriod $period): Collection
    {
        $merged = collect();

        foreach ($entriesByCar as $carEntries) {
            foreach ($this->bucketsForCar($carEntries, $period) as $bucket) {
                $merged[$bucket->label] = $merged->has($bucket->label)
                    ? $merged[$bucket->label]->merge($bucket)
                    : $bucket;
            }
        }

        return $merged->sortKeys()->values();
    }

    /**
     * All-time summary for one car, reusing the same FuelStretchService
     * output the period-bucketed table draws from, so totals/efficiency
     * can never disagree with that view.
     *
     * @param  Collection<int, FuelEntry>  $entries  All of ONE car's entries.
     */
    public function allTimeSummaryForCar(Collection $entries): CarFuelSummary
    {
        $result = $this->stretchService->compute($entries);

        return $this->summarize($result->rows, $result->stretches);
    }

    /**
     * All-time summary across every car, pooling each car's rows/stretches
     * after computing them independently — same reasoning as bucketsGlobal:
     * stretches must never be computed across merged multi-car entries.
     *
     * @param  Collection<int, Collection<int, FuelEntry>>  $entriesByCar  One entry collection per car.
     */
    public function allTimeSummaryGlobal(Collection $entriesByCar): CarFuelSummary
    {
        $rows = collect();
        $stretches = collect();

        foreach ($entriesByCar as $carEntries) {
            $result = $this->stretchService->compute($carEntries);
            $rows = $rows->merge($result->rows);
            $stretches = $stretches->merge($result->stretches);
        }

        return $this->summarize($rows, $stretches);
    }

    /**
     * @param  Collection<int, FuelEntryRow>  $rows
     * @param  Collection<int, FuelStretch>  $stretches
     */
    private function summarize(Collection $rows, Collection $stretches): CarFuelSummary
    {
        $totalAmount = (float) $rows->sum(fn ($row) => (float) $row->entry->amount);
        $totalLitres = (float) $rows->sum(fn ($row) => (float) $row->entry->litres_filled);
        $totalKm = (int) $rows->sum(fn ($row) => $row->kmSincePrevious ?? 0);
        $fillUpCount = $rows->count();

        $averageL100km = $stretches->isEmpty()
            ? null
            : $stretches->sum(fn ($stretch) => $stretch->l100km) / $stretches->count();

        $costPerKm = $totalKm > 0 ? $totalAmount / $totalKm : null;
        $averagePricePerLitre = $totalLitres > 0 ? $totalAmount / $totalLitres : 0.0;

        $priced = $rows->filter(fn ($row) => (float) $row->entry->litres_filled > 0);
        $cheapestFillUp = $priced->sortBy('pricePerLitre')->first();
        $priciestFillUp = $priced->sortByDesc('pricePerLitre')->first();

        return new CarFuelSummary(
            $totalAmount,
            $totalLitres,
            $totalKm,
            $averageL100km,
            $costPerKm,
            $averagePricePerLitre,
            $fillUpCount,
            $cheapestFillUp,
            $priciestFillUp,
            $this->byStation($rows),
            $this->byFuelType($rows, $stretches),
        );
    }

    /**
     * @param  Collection<int, FuelEntryRow>  $rows
     * @return Collection<int, StationBreakdown>
     */
    private function byStation(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn ($row) => $row->entry->gas_station)
            ->map(fn (Collection $group, string $gasStation) => new StationBreakdown(
                $gasStation,
                (float) $group->sum(fn ($row) => (float) $row->entry->amount),
                (float) $group->sum(fn ($row) => (float) $row->entry->litres_filled),
                $group->count(),
            ))
            ->values();
    }

    /**
     * @param  Collection<int, FuelEntryRow>  $rows
     * @param  Collection<int, FuelStretch>  $stretches
     * @return Collection<int, FuelTypeBreakdown>
     */
    private function byFuelType(Collection $rows, Collection $stretches): Collection
    {
        $l100kmSamplesByKey = $stretches
            ->groupBy(fn (FuelStretch $stretch) => $this->fuelTypeKey($this->carFuelTypeValue($stretch->closingEntry), $stretch->closingEntry->fuel_variant))
            ->map(fn (Collection $group) => $group->pluck('l100km')->all());

        return $rows
            ->groupBy(fn ($row) => $this->fuelTypeKey($this->carFuelTypeValue($row->entry), $row->entry->fuel_variant))
            ->map(function (Collection $group, string $key) use ($l100kmSamplesByKey) {
                $first = $group->first()->entry;
                $samples = $l100kmSamplesByKey->get($key, []);

                return new FuelTypeBreakdown(
                    $first->car?->fuel_type,
                    $first->fuel_variant,
                    (float) $group->sum(fn ($row) => (float) $row->entry->amount),
                    (float) $group->sum(fn ($row) => (float) $row->entry->litres_filled),
                    $samples === [] ? null : array_sum($samples) / count($samples),
                    $group->count(),
                );
            })
            ->values();
    }

    private function carFuelTypeValue(FuelEntry $entry): ?string
    {
        return $entry->car?->fuel_type?->value;
    }

    private function fuelTypeKey(?string $fuelType, ?string $fuelVariant): string
    {
        return ($fuelType ?? '').'|'.($fuelVariant ?? '');
    }

    /**
     * @param  Collection<int, FuelEntry>  $entries
     * @param  Collection<int, FuelStretch>  $stretches
     * @return Collection<int, AnalyticsBucket>
     */
    private function bucketize(Collection $entries, Collection $stretches, AnalyticsPeriod $period): Collection
    {
        $accumulated = [];

        foreach ($entries as $entry) {
            $label = $this->label($entry->date_of_filling, $period);
            $accumulated[$label]['amount'] = ($accumulated[$label]['amount'] ?? 0.0) + (float) $entry->amount;
            $accumulated[$label]['litres'] = ($accumulated[$label]['litres'] ?? 0.0) + (float) $entry->litres_filled;
            $accumulated[$label]['samples'] ??= [];
        }

        foreach ($stretches as $stretch) {
            // A stretch that spans multiple periods is attributed entirely
            // to the period containing its CLOSING full-fill's date — the
            // natural reading of "average efficiency for entries that
            // closed in this period."
            $label = $this->label($stretch->closingEntry->date_of_filling, $period);
            $accumulated[$label]['amount'] ??= 0.0;
            $accumulated[$label]['litres'] ??= 0.0;
            $accumulated[$label]['samples'][] = $stretch->l100km;
        }

        ksort($accumulated);

        return collect($accumulated)
            ->map(fn (array $data, string $label): AnalyticsBucket => new AnalyticsBucket(
                $label,
                $data['amount'],
                $data['litres'],
                $data['samples'],
            ))
            ->values();
    }

    private function label(Carbon $date, AnalyticsPeriod $period): string
    {
        return match ($period) {
            AnalyticsPeriod::Month => $date->format('Y-m'),
            AnalyticsPeriod::Quarter => $date->year.'-Q'.$date->quarter,
            AnalyticsPeriod::Year => (string) $date->year,
        };
    }
}
