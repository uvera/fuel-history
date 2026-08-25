<?php

namespace Tests\Unit;

use App\Models\FuelEntry;
use App\Services\FuelStretchService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Extends the app's TestCase (not bare PHPUnit\Framework\TestCase) only to
 * get Laravel's connection resolver bootstrapped — constructing/casting an
 * Eloquent model needs it even without ever querying. No database trait
 * (RefreshDatabase etc.) is used: this is a pure computation test, no
 * queries are ever issued.
 */
class FuelStretchServiceTest extends TestCase
{
    private FuelStretchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FuelStretchService;
    }

    /**
     * Builds an in-memory (never persisted) FuelEntry — this is a pure
     * unit test of the computation logic, no database needed.
     */
    private function entry(
        int $id,
        string $date,
        int $odometer,
        float $litres,
        float $amount,
        bool $isPartial = false,
        ?float $tripKm = null,
    ): FuelEntry {
        $entry = new FuelEntry([
            'gas_station' => 'Test',
            'litres_filled' => $litres,
            'amount' => $amount,
            'odometer_at_fill' => $odometer,
            'date_of_filling' => $date,
            'is_partial_fill' => $isPartial,
            'trip_km' => $tripKm,
        ]);
        $entry->id = $id;

        return $entry;
    }

    public function test_first_entry_ever_has_no_km_since_previous_or_accurate_l100km(): void
    {
        $entry = $this->entry(1, '2026-01-01', 10000, 40, 4000);

        $result = $this->service->compute(new Collection([$entry]));

        $row = $result->rows->first();
        $this->assertNull($row->kmSincePrevious);
        $this->assertNull($row->l100kmAccurate);
        $this->assertNull($row->l100kmTripEstimate);
        $this->assertEqualsWithDelta(100.0, $row->pricePerLitre, 0.0001);
        $this->assertCount(0, $result->stretches);
    }

    public function test_price_per_litre_is_always_amount_over_litres(): void
    {
        $entry = $this->entry(1, '2026-01-01', 10000, 37.5, 4500);

        $row = $this->service->compute(new Collection([$entry]))->rows->first();

        $this->assertEqualsWithDelta(120.0, $row->pricePerLitre, 0.0001);
    }

    public function test_two_consecutive_full_fills_compute_km_since_previous_and_accurate_l100km(): void
    {
        $first = $this->entry(1, '2026-01-01', 10000, 40, 4000);
        $second = $this->entry(2, '2026-01-15', 10500, 45, 4500);

        $result = $this->service->compute(new Collection([$first, $second]));

        $rows = $result->rows->values();
        $this->assertNull($rows[0]->kmSincePrevious);
        $this->assertSame(500, $rows[1]->kmSincePrevious);
        // 45L / 500km * 100 = 9.0 L/100km
        $this->assertEqualsWithDelta(9.0, $rows[1]->l100kmAccurate, 0.0001);
        $this->assertCount(1, $result->stretches);
        $this->assertSame(2, $result->stretches->first()->closingEntry->id);
    }

    public function test_partial_fill_between_two_full_fills_sums_litres_and_spans_back_to_previous_full_fill(): void
    {
        $full1 = $this->entry(1, '2026-01-01', 10000, 40, 4000);
        $partial = $this->entry(2, '2026-01-08', 10200, 15, 1500, isPartial: true);
        $full2 = $this->entry(3, '2026-01-15', 10500, 30, 3000);

        $result = $this->service->compute(new Collection([$full1, $partial, $full2]));

        $rows = $result->rows->values();

        // Partial row: no accurate L/100km of its own, no km-since-previous suppressed.
        $this->assertNull($rows[1]->l100kmAccurate);
        $this->assertSame(200, $rows[1]->kmSincePrevious);

        // Closing full fill: (15 + 30) litres over (10500 - 10000) = 500km.
        $this->assertSame(300, $rows[2]->kmSincePrevious);
        $this->assertEqualsWithDelta(9.0, $rows[2]->l100kmAccurate, 0.0001); // 45L / 500km * 100

        $this->assertCount(1, $result->stretches);
        $stretch = $result->stretches->first();
        $this->assertEqualsWithDelta(45.0, $stretch->totalLitres, 0.0001);
        $this->assertSame(500, $stretch->odometerSpan);
    }

    public function test_multiple_consecutive_partial_fills_all_sum_into_the_closing_stretch(): void
    {
        $full1 = $this->entry(1, '2026-01-01', 10000, 40, 4000);
        $partial1 = $this->entry(2, '2026-01-05', 10100, 10, 1000, isPartial: true);
        $partial2 = $this->entry(3, '2026-01-10', 10200, 12, 1200, isPartial: true);
        $partial3 = $this->entry(4, '2026-01-12', 10250, 8, 800, isPartial: true);
        $full2 = $this->entry(5, '2026-01-15', 10500, 20, 2000);

        $result = $this->service->compute(new Collection([$full1, $partial1, $partial2, $partial3, $full2]));

        $stretch = $result->stretches->first();
        // 10 + 12 + 8 + 20 = 50 litres over (10500 - 10000) = 500km.
        $this->assertEqualsWithDelta(50.0, $stretch->totalLitres, 0.0001);
        $this->assertSame(500, $stretch->odometerSpan);
        $this->assertEqualsWithDelta(10.0, $stretch->l100km, 0.0001);
    }

    public function test_trip_estimate_present_only_on_partial_rows_with_trip_km_set(): void
    {
        $full1 = $this->entry(1, '2026-01-01', 10000, 40, 4000);
        $partialWithTrip = $this->entry(2, '2026-01-05', 10100, 8, 800, isPartial: true, tripKm: 100);
        $partialWithoutTrip = $this->entry(3, '2026-01-08', 10200, 6, 600, isPartial: true, tripKm: null);
        $full2 = $this->entry(4, '2026-01-15', 10500, 20, 2000);

        $result = $this->service->compute(new Collection([$full1, $partialWithTrip, $partialWithoutTrip, $full2]));
        $rows = $result->rows->values();

        // 8L / 100km * 100 = 8.0 L/100km
        $this->assertEqualsWithDelta(8.0, $rows[1]->l100kmTripEstimate, 0.0001);
        $this->assertNull($rows[1]->l100kmAccurate);

        $this->assertNull($rows[2]->l100kmTripEstimate);

        // Full-fill rows never get a trip estimate.
        $this->assertNull($rows[0]->l100kmTripEstimate);
        $this->assertNull($rows[3]->l100kmTripEstimate);
    }

    public function test_car_with_only_partial_fills_never_computes_accurate_l100km(): void
    {
        $entries = new Collection([
            $this->entry(1, '2026-01-01', 10000, 20, 2000, isPartial: true),
            $this->entry(2, '2026-01-08', 10200, 15, 1500, isPartial: true, tripKm: 200),
            $this->entry(3, '2026-01-15', 10400, 18, 1800, isPartial: true),
        ]);

        $result = $this->service->compute($entries);

        $this->assertTrue($result->rows->every(fn ($row) => $row->l100kmAccurate === null));
        $this->assertCount(0, $result->stretches);
    }

    public function test_first_full_fill_ever_has_no_accurate_l100km_but_still_appears_as_no_stretch(): void
    {
        // Only one full fill exists — nothing to compare it against.
        $entry = $this->entry(1, '2026-01-01', 10000, 40, 4000);

        $result = $this->service->compute(new Collection([$entry]));

        $this->assertNull($result->rows->first()->l100kmAccurate);
        $this->assertCount(0, $result->stretches);
    }

    public function test_ties_on_identical_timestamps_are_broken_by_id_for_deterministic_ordering(): void
    {
        $second = $this->entry(2, '2026-01-01 10:00:00', 10100, 20, 2000);
        $first = $this->entry(1, '2026-01-01 10:00:00', 10000, 40, 4000);

        // Passed in id-descending order deliberately, to prove the service
        // sorts by (date_of_filling, id) itself rather than trusting
        // caller order.
        $result = $this->service->compute(new Collection([$second, $first]));

        $rows = $result->rows->values();
        $this->assertSame(1, $rows[0]->entry->id);
        $this->assertSame(2, $rows[1]->entry->id);
        $this->assertSame(100, $rows[1]->kmSincePrevious);
    }

    public function test_zero_or_negative_odometer_span_does_not_produce_a_stretch_or_divide_by_zero(): void
    {
        // Odometer entered incorrectly (didn't increase, or decreased) —
        // must not produce an infinite/negative L/100km or throw.
        $full1 = $this->entry(1, '2026-01-01', 10000, 40, 4000);
        $full2 = $this->entry(2, '2026-01-15', 10000, 30, 3000); // same odometer

        $result = $this->service->compute(new Collection([$full1, $full2]));

        $this->assertNull($result->rows->values()[1]->l100kmAccurate);
        $this->assertCount(0, $result->stretches);
    }

    public function test_empty_entry_collection_returns_empty_result_without_error(): void
    {
        $result = $this->service->compute(new Collection);

        $this->assertCount(0, $result->rows);
        $this->assertCount(0, $result->stretches);
    }
}
