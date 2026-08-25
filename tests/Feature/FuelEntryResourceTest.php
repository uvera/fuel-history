<?php

namespace Tests\Feature;

use App\Filament\Resources\FuelEntries\FuelEntryResource;
use App\Filament\Resources\FuelEntries\Pages\CreateFuelEntry;
use App\Filament\Resources\FuelEntries\Pages\EditFuelEntry;
use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FuelEntryResourceTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = User::ROLE_USER): User
    {
        return User::factory()->create([
            'role' => $role,
            'app_authentication_secret' => 'enrolled',
        ]);
    }

    public function test_any_authenticated_user_can_log_a_fuel_entry_for_any_car(): void
    {
        $car = Car::factory()->create();
        $user = $this->user();

        $this->actingAs($user);

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'gas_station' => 'Test Petrol Station',
                'litres_filled' => 42.5,
                'amount' => 5000,
                'odometer_at_fill' => 12345,
                'date_of_filling' => now(),
                'is_partial_fill' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = FuelEntry::firstOrFail();
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame($car->id, $entry->car_id);
        $this->assertSame('RSD', $entry->currency);
    }

    public function test_fuel_entry_can_be_logged_without_an_odometer_reading(): void
    {
        // Receipt-only entry — no photo of the odometer to read it from.
        $car = Car::factory()->create();
        $this->actingAs($this->user());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'gas_station' => 'Test Petrol Station',
                'litres_filled' => 42.5,
                'amount' => 5000,
                'odometer_at_fill' => null,
                'date_of_filling' => now(),
                'is_partial_fill' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = FuelEntry::firstOrFail();
        $this->assertNull($entry->odometer_at_fill);
    }

    public function test_partial_fill_shows_trip_km_field_and_it_persists(): void
    {
        $car = Car::factory()->create();
        $this->actingAs($this->user());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'gas_station' => 'Test Petrol Station',
                'litres_filled' => 20,
                'amount' => 2500,
                'odometer_at_fill' => 10000,
                'date_of_filling' => now(),
                'is_partial_fill' => true,
                'trip_km' => 150.5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = FuelEntry::firstOrFail();
        $this->assertTrue($entry->is_partial_fill);
        $this->assertEquals(150.5, $entry->trip_km);
    }

    public function test_owner_can_edit_their_own_entry(): void
    {
        $owner = $this->user();
        $entry = FuelEntry::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);

        $this->get(FuelEntryResource::getUrl('edit', ['record' => $entry]))->assertOk();

        Livewire::test(EditFuelEntry::class, ['record' => $entry->id])
            ->fillForm(['gas_station' => 'Updated Station'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated Station', $entry->refresh()->gas_station);
    }

    public function test_non_owner_regular_user_cannot_edit_someone_elses_entry(): void
    {
        $owner = $this->user();
        $otherUser = $this->user();
        $entry = FuelEntry::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherUser);

        $this->get(FuelEntryResource::getUrl('edit', ['record' => $entry]))->assertForbidden();
    }

    public function test_admin_can_edit_any_entry_even_if_not_the_owner(): void
    {
        $owner = $this->user();
        $admin = $this->user(User::ROLE_ADMIN);
        $entry = FuelEntry::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($admin);

        $this->get(FuelEntryResource::getUrl('edit', ['record' => $entry]))->assertOk();
    }

    public function test_non_owner_regular_user_cannot_delete_someone_elses_entry(): void
    {
        $owner = $this->user();
        $otherUser = $this->user();
        $entry = FuelEntry::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($otherUser->can('delete', $entry));
        $this->assertTrue($owner->can('delete', $entry));
    }
}
