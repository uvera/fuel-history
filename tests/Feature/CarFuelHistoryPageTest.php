<?php

namespace Tests\Feature;

use App\Filament\Pages\CarFuelHistory;
use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CarFuelHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_with_computed_virtual_columns_for_a_real_seeded_sequence(): void
    {
        $user = User::factory()->create([
            'app_authentication_secret' => 'enrolled',
        ]);
        $car = Car::factory()->create(['name' => 'Test Wagon']);

        $full1 = FuelEntry::factory()->create([
            'car_id' => $car->id,
            'user_id' => $user->id,
            'odometer_at_fill' => 10000,
            'litres_filled' => 40,
            'amount' => 4000,
            'date_of_filling' => '2026-01-01',
            'is_partial_fill' => false,
        ]);

        $partial = FuelEntry::factory()->create([
            'car_id' => $car->id,
            'user_id' => $user->id,
            'odometer_at_fill' => 10200,
            'litres_filled' => 15,
            'amount' => 1500,
            'date_of_filling' => '2026-01-08',
            'is_partial_fill' => true,
            'trip_km' => 200,
        ]);

        $full2 = FuelEntry::factory()->create([
            'car_id' => $car->id,
            'user_id' => $user->id,
            'odometer_at_fill' => 10500,
            'litres_filled' => 30,
            'amount' => 3000,
            'date_of_filling' => '2026-01-15',
            'is_partial_fill' => false,
        ]);

        $this->actingAs($user);

        $this->get(CarFuelHistory::getUrl(['record' => $car]))->assertOk();

        $component = Livewire::test(CarFuelHistory::class, ['record' => $car]);

        // 45L (15 + 30) over 500km (10500 - 10000) * 100 = 9.0
        $component->assertSee('9.00');
        // Trip estimate on the partial row: 15L / 200km * 100 = 7.50
        $component->assertSee('7.50');
        // Price per litre on full2: 3000 / 30 = 100.00
        $component->assertSee('100.00');
    }

    public function test_regular_user_can_view_the_page_read_only(): void
    {
        $user = User::factory()->create(['app_authentication_secret' => 'enrolled', 'role' => User::ROLE_USER]);
        $car = Car::factory()->create();

        $this->actingAs($user)
            ->get(CarFuelHistory::getUrl(['record' => $car]))
            ->assertOk();
    }
}
