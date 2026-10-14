<?php

namespace Tests\Unit;

use App\Models\FuelEntry;
use App\Services\FillIntervalConsumption;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Extends the app's TestCase (not bare PHPUnit\Framework\TestCase) only to
 * get Laravel's connection resolver bootstrapped — constructing/casting an
 * Eloquent model needs it even without ever querying. No database trait
 * (RefreshDatabase etc.) is used: this is a pure computation test, no
 * queries are ever issued.
 */
class FillIntervalConsumptionTest extends TestCase
{
    private FillIntervalConsumption $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FillIntervalConsumption;
    }

    /**
     * Builds an in-memory (never persisted) FuelEntry.
     */
    private function entry(
        int $id,
        string $date,
        ?int $odometer,
        float $litres,
        int $carId = 1,
        bool $isPartial = false,
    ): FuelEntry {
        $entry = new FuelEntry([
            'car_id' => $carId,
            'gas_station' => 'Test',
            'litres_filled' => $litres,
            'amount' => $litres * 100,
            'odometer_at_fill' => $odometer,
            'date_of_filling' => $date,
            'is_partial_fill' => $isPartial,
        ]);
        $entry->id = $id;

        return $entry;
    }

    public function test_consecutive_fills_use_only_the_end_fills_litres(): void
    {
        $start = $this->entry(1, '2026-01-01', 10000, 40);
        $end = $this->entry(2, '2026-01-15', 10500, 45);

        $result = $this->service->compute(new Collection([$end, $start]), $start, $end);

        $this->assertNull($result->message);
        $this->assertEqualsWithDelta(45.0, $result->litres, 0.0001);
        $this->assertSame(500, $result->kilometres);
        $this->assertEqualsWithDelta(9.0, $result->l100km, 0.0001);
    }

    public function test_fills_in_between_are_included_and_fills_outside_are_not(): void
    {
        $before = $this->entry(1, '2025-12-01', 9000, 99);
        $start = $this->entry(2, '2026-01-01', 10000, 40);
        $middle = $this->entry(3, '2026-01-08', 10200, 20);
        $end = $this->entry(4, '2026-01-15', 10500, 30);
        $after = $this->entry(5, '2026-01-20', 10800, 77);

        $result = $this->service->compute(
            new Collection([$after, $middle, $before, $end, $start]),
            $start,
            $end,
        );

        // 20 + 30, not the start's 40, the earlier 99, or the later 77.
        $this->assertNull($result->message);
        $this->assertEqualsWithDelta(50.0, $result->litres, 0.0001);
        $this->assertSame(500, $result->kilometres);
        $this->assertEqualsWithDelta(10.0, $result->l100km, 0.0001);
    }

    public function test_partials_between_the_endpoints_are_included(): void
    {
        $start = $this->entry(1, '2026-01-01', 10000, 40);
        $partial = $this->entry(2, '2026-01-08', 10200, 15, isPartial: true);
        $end = $this->entry(3, '2026-01-15', 10500, 30);

        $result = $this->service->compute(new Collection([$start, $partial, $end]), $start, $end);

        // 15 + 30 over (10500 - 10000). The start fill's 40 L is excluded.
        $this->assertNull($result->message);
        $this->assertEqualsWithDelta(45.0, $result->litres, 0.0001);
        $this->assertSame(500, $result->kilometres);
        $this->assertEqualsWithDelta(9.0, $result->l100km, 0.0001);
    }

    public function test_intermediate_fills_without_an_odometer_still_count_toward_litres(): void
    {
        $start = $this->entry(1, '2026-01-01', 10000, 40);
        $unknown = $this->entry(2, '2026-01-08', null, 15);
        $end = $this->entry(3, '2026-01-15', 10500, 30);

        $result = $this->service->compute(new Collection([$start, $unknown, $end]), $start, $end);

        $this->assertNull($result->message);
        $this->assertEqualsWithDelta(45.0, $result->litres, 0.0001);
        $this->assertSame(500, $result->kilometres);
        $this->assertEqualsWithDelta(9.0, $result->l100km, 0.0001);
    }

    public function test_same_timestamp_is_ordered_by_id(): void
    {
        $earlier = $this->entry(1, '2026-01-01 10:00:00', 10000, 40);
        $later = $this->entry(2, '2026-01-01 10:00:00', 10100, 10);

        $result = $this->service->compute(new Collection([$later, $earlier]), $earlier, $later);

        $this->assertNull($result->message);
        $this->assertEqualsWithDelta(10.0, $result->litres, 0.0001);
        $this->assertSame(100, $result->kilometres);

        $refused = $this->service->compute(new Collection([$earlier, $later]), $later, $earlier);

        $this->assertSame(FillIntervalConsumption::MESSAGE_NOT_CHRONOLOGICAL, $refused->message);
    }

    public function test_refuses_when_the_two_fills_are_the_same(): void
    {
        $start = $this->entry(1, '2026-01-01', 10000, 40);
        $end = $this->entry(2, '2026-01-15', 10500, 30);

        $result = $this->service->compute(new Collection([$start, $end]), $start, $start);

        $this->assertSame(FillIntervalConsumption::MESSAGE_SAME_FILL, $result->message);
        $this->assertNull($result->litres);
        $this->assertNull($result->kilometres);
        $this->assertNull($result->l100km);
    }

    public function test_refuses_when_fills_are_not_in_chronological_order(): void
    {
        $start = $this->entry(1, '2026-01-01', 10000, 40);
        $end = $this->entry(2, '2026-01-15', 10500, 30);

        $result = $this->service->compute(new Collection([$start, $end]), $end, $start);

        $this->assertSame(FillIntervalConsumption::MESSAGE_NOT_CHRONOLOGICAL, $result->message);
        $this->assertNull($result->l100km);
    }

    public function test_refuses_when_a_fill_belongs_to_another_car(): void
    {
        $start = $this->entry(1, '2026-01-01', 10000, 40);
        $end = $this->entry(2, '2026-01-15', 10500, 30);
        $other = $this->entry(3, '2026-01-20', 11000, 25, carId: 2);

        $result = $this->service->compute(new Collection([$start, $end]), $start, $other);

        $this->assertSame(FillIntervalConsumption::MESSAGE_OTHER_CAR, $result->message);
        $this->assertNull($result->litres);
        $this->assertNull($result->kilometres);
        $this->assertNull($result->l100km);
    }

    public function test_refuses_when_the_start_fill_has_no_odometer(): void
    {
        $start = $this->entry(1, '2026-01-01', null, 40);
        $end = $this->entry(2, '2026-01-15', 10500, 30);

        $result = $this->service->compute(new Collection([$start, $end]), $start, $end);

        $this->assertSame(FillIntervalConsumption::MESSAGE_MISSING_ODOMETER, $result->message);
        $this->assertNull($result->l100km);
    }

    public function test_refuses_when_the_end_fill_has_no_odometer(): void
    {
        $start = $this->entry(1, '2026-01-01', 10000, 40);
        $end = $this->entry(2, '2026-01-15', null, 30);

        $result = $this->service->compute(new Collection([$start, $end]), $start, $end);

        $this->assertSame(FillIntervalConsumption::MESSAGE_MISSING_ODOMETER, $result->message);
        $this->assertNull($result->l100km);
    }

    public function test_refuses_when_the_odometer_gap_is_not_positive(): void
    {
        $start = $this->entry(1, '2026-01-01', 10000, 40);
        $same = $this->entry(2, '2026-01-15', 10000, 30);
        $backwards = $this->entry(3, '2026-01-20', 9900, 25);

        $zero = $this->service->compute(new Collection([$start, $same]), $start, $same);
        $negative = $this->service->compute(new Collection([$start, $backwards]), $start, $backwards);

        $this->assertSame(FillIntervalConsumption::MESSAGE_ODOMETER_GAP, $zero->message);
        $this->assertNull($zero->l100km);
        $this->assertSame(FillIntervalConsumption::MESSAGE_ODOMETER_GAP, $negative->message);
        $this->assertNull($negative->l100km);
    }
}
