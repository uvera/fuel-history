<?php

namespace Tests\Feature;

use App\Enums\FuelType;
use App\Filament\Pages\CarFuelHistory;
use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\User;
use App\Services\FillIntervalConsumption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FillIntervalConsumptionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_computed_consumption_after_both_fills_are_chosen(): void
    {
        $user = User::factory()->create([
            'app_authentication_secret' => 'enrolled',
        ]);
        $car = Car::factory()->create();

        $shared = [
            'car_id' => $car->id,
            'user_id' => $user->id,
            'fuel_type' => FuelType::Gasoline,
            'fuel_variant' => '95',
            'is_partial_fill' => false,
        ];
        FuelEntry::factory()->create([
            ...$shared,
            'gas_station' => 'Early Station',
            'odometer_at_fill' => 9000,
            'litres_filled' => 10,
            'amount' => 1000,
            'date_of_filling' => '2025-12-01',
        ]);
        $start = FuelEntry::factory()->create([
            ...$shared,
            'gas_station' => 'North Station',
            'odometer_at_fill' => 10000,
            'litres_filled' => 40,
            'amount' => 4000,
            'date_of_filling' => '2026-01-01',
        ]);
        FuelEntry::factory()->create([
            ...$shared,
            'gas_station' => 'Mid Station',
            'odometer_at_fill' => 10400,
            'litres_filled' => 22,
            'amount' => 2200,
            'date_of_filling' => '2026-01-10',
        ]);
        $end = FuelEntry::factory()->create([
            ...$shared,
            'gas_station' => 'South Station',
            'odometer_at_fill' => 11200,
            'litres_filled' => 48,
            'amount' => 4800,
            'date_of_filling' => '2026-01-20',
        ]);

        $otherCar = Car::factory()->create();
        FuelEntry::factory()->create([
            'car_id' => $otherCar->id,
            'user_id' => $user->id,
            'fuel_type' => FuelType::Diesel,
            'fuel_variant' => 'B7',
            'gas_station' => 'Other Car Only',
            'odometer_at_fill' => 5000,
            'litres_filled' => 10,
            'amount' => 1000,
            'date_of_filling' => '2026-01-05',
        ]);

        $this->actingAs($user);

        // (22 + 48) / 1200km * 100 = 5.833…. The full-tank column is 4,00
        // then 5,50 then 6,00, and all-time distance is 2.200 km, so 5,83,
        // 70,000 litres, and 1.200 km appear only in this comparison.
        $component = Livewire::test(CarFuelHistory::class, ['record' => $car]);

        $component->assertSee('2026-01-01 00:00 — North Station — 10000 km');
        $component->assertDontSee('Other Car Only');
        $component->assertDontSee('Kilometres');
        $component->assertDontSee('5,83');
        $component->assertDontSee('70,000');
        $component->assertDontSee('1.200');

        $component->set('startFillId', $start->id);
        $component->assertDontSee('5,83');

        $component
            ->set('endFillId', $end->id)
            ->assertSee('Kilometres')
            ->assertSee('5,83')
            ->assertSee('70,000')
            ->assertSee('1.200');

        $component
            ->set('endFillId', $start->id)
            ->assertSee(FillIntervalConsumption::MESSAGE_SAME_FILL)
            ->assertDontSee('5,83');
    }
}
