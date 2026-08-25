<?php

namespace Tests\Unit;

use App\Models\FuelEntry;
use App\Services\AnalyticsPeriod;
use App\Services\AnalyticsService;
use App\Services\FuelStretchService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    private AnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnalyticsService(new FuelStretchService);
    }

    private function entry(
        int $id,
        string $date,
        int $odometer,
        float $litres,
        float $amount,
        bool $isPartial = false,
    ): FuelEntry {
        $entry = new FuelEntry([
            'gas_station' => 'Test',
            'litres_filled' => $litres,
            'amount' => $amount,
            'odometer_at_fill' => $odometer,
            'date_of_filling' => $date,
            'is_partial_fill' => $isPartial,
        ]);
        $entry->id = $id;

        return $entry;
    }

    public function test_buckets_spend_and_litres_by_month(): void
    {
        $entries = new Collection([
            $this->entry(1, '2026-01-05', 10000, 40, 4000),
            $this->entry(2, '2026-01-20', 10400, 40, 4200),
            $this->entry(3, '2026-02-03', 10800, 35, 3800),
        ]);

        $buckets = $this->service->bucketsForCar($entries, AnalyticsPeriod::Month);

        $this->assertCount(2, $buckets);
        $jan = $buckets->firstWhere('label', '2026-01');
        $feb = $buckets->firstWhere('label', '2026-02');

        $this->assertEqualsWithDelta(8200.0, $jan->totalAmount, 0.0001);
        $this->assertEqualsWithDelta(80.0, $jan->totalLitres, 0.0001);
        $this->assertEqualsWithDelta(3800.0, $feb->totalAmount, 0.0001);
    }

    public function test_buckets_by_quarter_and_year(): void
    {
        $entries = new Collection([
            $this->entry(1, '2026-02-01', 10000, 40, 4000), // Q1
            $this->entry(2, '2026-05-01', 10400, 40, 4000), // Q2
            $this->entry(3, '2027-01-01', 10800, 40, 4000), // next year
        ]);

        $quarters = $this->service->bucketsForCar($entries, AnalyticsPeriod::Quarter);
        $this->assertSame(['2026-Q1', '2026-Q2', '2027-Q1'], $quarters->pluck('label')->all());

        $years = $this->service->bucketsForCar($entries, AnalyticsPeriod::Year);
        $this->assertSame(['2026', '2027'], $years->pluck('label')->all());
    }

    public function test_a_stretch_spanning_two_months_is_attributed_to_the_closing_month(): void
    {
        $entries = new Collection([
            $this->entry(1, '2026-01-25', 10000, 40, 4000), // opens the stretch, in January
            $this->entry(2, '2026-02-05', 10500, 30, 3000), // closes it, in February
        ]);

        $buckets = $this->service->bucketsForCar($entries, AnalyticsPeriod::Month);

        $jan = $buckets->firstWhere('label', '2026-01');
        $feb = $buckets->firstWhere('label', '2026-02');

        $this->assertNull($jan->averageL100km());
        // 30L / 500km * 100 = 6.0
        $this->assertEqualsWithDelta(6.0, $feb->averageL100km(), 0.0001);
    }

    public function test_empty_period_has_zero_totals_and_null_average_not_a_crash(): void
    {
        $buckets = $this->service->bucketsForCar(new Collection, AnalyticsPeriod::Month);

        $this->assertCount(0, $buckets);
    }

    public function test_global_sums_spend_and_litres_across_cars_without_error(): void
    {
        $carA = new Collection([
            $this->entry(1, '2026-01-01', 10000, 40, 4000),
        ]);
        $carB = new Collection([
            $this->entry(2, '2026-01-10', 5000, 20, 2000),
        ]);

        $buckets = $this->service->bucketsGlobal(new Collection([$carA, $carB]), AnalyticsPeriod::Month);

        $this->assertCount(1, $buckets);
        $this->assertEqualsWithDelta(6000.0, $buckets->first()->totalAmount, 0.0001);
        $this->assertEqualsWithDelta(60.0, $buckets->first()->totalLitres, 0.0001);
    }

    public function test_global_does_not_cross_contaminate_stretches_between_cars(): void
    {
        // If entries from both cars were merged into one chronological
        // stream before computing stretches, car B's single full fill
        // would incorrectly "close" a stretch spanning car A's odometer
        // reading — producing a nonsensical L/100km. Each car must be
        // computed independently.
        $carA = new Collection([
            $this->entry(1, '2026-01-01', 10000, 40, 4000), // no prior full fill on car A -> no stretch
        ]);
        $carB = new Collection([
            $this->entry(2, '2026-01-02', 999999999, 999, 1), // wildly different odometer scale
        ]);

        $buckets = $this->service->bucketsGlobal(new Collection([$carA, $carB]), AnalyticsPeriod::Month);

        // Neither car has two full fills yet, so no stretch should exist
        // anywhere — proving the odometer scales were never compared
        // against each other.
        $this->assertCount(1, $buckets);
        $this->assertNull($buckets->first()->averageL100km());
    }

    public function test_global_pools_raw_stretch_samples_rather_than_averaging_per_car_averages(): void
    {
        // Car A: one stretch at 10.0 L/100km.
        $carA = new Collection([
            $this->entry(1, '2026-01-01', 10000, 40, 4000),
            $this->entry(2, '2026-01-10', 10500, 50, 5000), // 50L/500km*100 = 10.0
        ]);

        // Car B: three stretches at 4.0, 4.0, 4.0 -- but if we instead fed
        // it stretches averaging to 4.0 via a DIFFERENT sample distribution
        // (e.g. two very different values averaging to the same per-car
        // mean), naive average-of-per-car-averages vs. pooled-sample
        // average would diverge. Use 2.0 and 6.0 (mean 4.0, same as
        // uniform 4.0s) to make the distinction observable if the
        // implementation used weighted-by-car instead of pooled samples.
        $carB = new Collection([
            $this->entry(3, '2026-01-01', 20000, 20, 2000),
            $this->entry(4, '2026-01-05', 20500, 10, 1000), // 10L/500km*100 = 2.0
            $this->entry(5, '2026-01-10', 21000, 30, 3000), // 30L/500km*100 = 6.0
        ]);

        $buckets = $this->service->bucketsGlobal(new Collection([$carA, $carB]), AnalyticsPeriod::Month);

        // Pooled samples: [10.0, 2.0, 6.0] -> mean = 6.0.
        // (A naive average of per-car averages would give (10.0 + 4.0) / 2 = 7.0 instead.)
        $this->assertEqualsWithDelta(6.0, $buckets->first()->averageL100km(), 0.0001);
    }
}
