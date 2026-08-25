<?php

namespace App\Services;

use App\Models\FuelEntry;
use App\Services\Data\AnalyticsBucket;
use App\Services\Data\FuelStretch;
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
