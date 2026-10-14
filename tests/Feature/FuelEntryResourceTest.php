<?php

namespace Tests\Feature;

use App\Enums\FuelType;
use App\Filament\Resources\FuelEntries\FuelEntryResource;
use App\Filament\Resources\FuelEntries\Pages\CreateFuelEntry;
use App\Filament\Resources\FuelEntries\Pages\EditFuelEntry;
use App\Filament\Resources\FuelEntries\Pages\ListFuelEntries;
use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\FuelStation;
use App\Models\FuelVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /**
     * @return array{0: FuelStation, 1: ?FuelVariant}
     */
    private function catalog(string $station, ?string $variant = '95'): array
    {
        $fuelStation = FuelStation::factory()->create(['name' => $station]);
        $fuelVariant = $variant === null
            ? null
            : FuelVariant::factory()->create([
                'fuel_station_id' => $fuelStation->id,
                'name' => $variant,
            ]);

        return [$fuelStation, $fuelVariant];
    }

    public function test_any_authenticated_user_can_log_a_fuel_entry_for_any_car(): void
    {
        $car = Car::factory()->create();
        $user = $this->user();
        [$station, $variant] = $this->catalog('Test Petrol Station', '95');

        $this->actingAs($user);

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'fuel_station_id' => $station->id,
                'fuel_variant_id' => $variant->id,
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
        $this->assertSame($station->id, $entry->fuel_station_id);
        $this->assertSame($variant->id, $entry->fuel_variant_id);
        $this->assertSame('Test Petrol Station', $entry->gas_station);
        $this->assertSame('95', $entry->fuel_variant);
    }

    public function test_fuel_entry_can_be_logged_without_an_odometer_reading(): void
    {
        // Receipt-only entry — no photo of the odometer to read it from.
        $car = Car::factory()->create();
        [$station, $variant] = $this->catalog('Test Petrol Station', '95');
        $this->actingAs($this->user());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'fuel_station_id' => $station->id,
                'fuel_variant_id' => $variant->id,
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
        [$station, $variant] = $this->catalog('Test Petrol Station', '95');
        $this->actingAs($this->user());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'fuel_station_id' => $station->id,
                'fuel_variant_id' => $variant->id,
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

        [$station, $variant] = $this->catalog('Updated Station', '98');

        Livewire::test(EditFuelEntry::class, ['record' => $entry->id])
            ->fillForm([
                'fuel_station_id' => $station->id,
                'fuel_variant_id' => $variant->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $entry->refresh();
        $this->assertSame('Updated Station', $entry->gas_station);
        $this->assertSame($station->id, $entry->fuel_station_id);
        $this->assertSame('98', $entry->fuel_variant);
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

    public function test_creating_a_non_electric_entry_without_fuel_variant_fails_validation(): void
    {
        $car = Car::factory()->create(['fuel_type' => FuelType::Diesel]);
        [$station] = $this->catalog('Test Petrol Station', null);
        $this->actingAs($this->user());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'fuel_station_id' => $station->id,
                'litres_filled' => 42.5,
                'amount' => 5000,
                'odometer_at_fill' => 12345,
                'date_of_filling' => now(),
            ])
            ->call('create')
            ->assertHasFormErrors(['fuel_variant_id']);
    }

    public function test_creating_an_electric_entry_without_fuel_variant_succeeds(): void
    {
        $car = Car::factory()->create(['fuel_type' => FuelType::Electric]);
        [$station] = $this->catalog('Charging Station', null);
        $this->actingAs($this->user());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'fuel_station_id' => $station->id,
                'litres_filled' => 42.5,
                'amount' => 5000,
                'odometer_at_fill' => 12345,
                'date_of_filling' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = FuelEntry::firstOrFail();
        $this->assertSame(FuelType::Electric, $entry->car->fuel_type);
        $this->assertNull($entry->fuel_variant);
        $this->assertNull($entry->fuel_variant_id);
        $this->assertSame($station->id, $entry->fuel_station_id);
    }

    public function test_the_list_page_renders_an_entry_with_a_null_variant_using_the_cars_fuel_type(): void
    {
        $car = Car::factory()->create(['name' => 'Legacy Car', 'fuel_type' => FuelType::Gasoline]);
        $user = $this->user();
        $station = FuelStation::factory()->create(['name' => 'Legacy Station']);

        // Raw insert bypassing the model/validation layer entirely --
        // fuel_variant stays nullable even though the form requires it
        // for a non-electric car.
        DB::table('fuel_entries')->insert([
            'car_id' => $car->id,
            'user_id' => $user->id,
            'fuel_station_id' => $station->id,
            'fuel_variant_id' => null,
            'fuel_variant' => null,
            'gas_station' => 'Legacy Station',
            'litres_filled' => 10,
            'amount' => 1000,
            'odometer_at_fill' => 500,
            'date_of_filling' => now(),
            'is_partial_fill' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $this->get(FuelEntryResource::getUrl('index'))->assertOk();

        Livewire::test(ListFuelEntries::class)
            ->assertOk()
            ->assertSee('Legacy Station')
            ->assertSee('Gasoline');
    }

    public function test_a_new_station_and_variant_can_be_created_from_the_fuel_entry_form(): void
    {
        $car = Car::factory()->create();
        $this->actingAs($this->user());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'litres_filled' => 42.5,
                'amount' => 5000,
                'odometer_at_fill' => 12345,
                'date_of_filling' => now(),
            ])
            ->callFormComponentAction('fuel_station_id', 'createOption', data: [
                'name' => '  OMV  ',
            ])
            ->assertHasNoFormComponentActionErrors()
            ->callFormComponentAction('fuel_variant_id', 'createOption', data: [
                'name' => '95',
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = FuelEntry::firstOrFail();
        $this->assertSame('OMV', $entry->gas_station);
        $this->assertSame('95', $entry->fuel_variant);
        $this->assertSame(1, FuelStation::query()->count());
        $this->assertSame(1, FuelVariant::query()->count());

        Livewire::test(CreateFuelEntry::class)
            ->fillForm([
                'car_id' => $car->id,
                'litres_filled' => 10,
                'amount' => 1500,
                'odometer_at_fill' => 12400,
                'date_of_filling' => now(),
            ])
            ->callFormComponentAction('fuel_station_id', 'createOption', data: [
                'name' => 'omv',
            ])
            ->assertHasNoFormComponentActionErrors()
            ->callFormComponentAction('fuel_variant_id', 'createOption', data: [
                'name' => '95',
            ])
            ->assertHasNoFormComponentActionErrors()
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, FuelStation::query()->count());
        $this->assertSame(1, FuelVariant::query()->count());
        $this->assertSame('OMV', FuelEntry::query()->orderByDesc('id')->firstOrFail()->gas_station);
    }
}
