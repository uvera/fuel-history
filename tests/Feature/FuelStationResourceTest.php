<?php

namespace Tests\Feature;

use App\Filament\Resources\FuelStations\Pages\CreateFuelStation;
use App\Filament\Resources\FuelStations\Pages\EditFuelStation;
use App\Filament\Resources\FuelStations\Pages\ListFuelStations;
use App\Filament\Resources\FuelStations\RelationManagers\FuelVariantsRelationManager;
use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\FuelStation;
use App\Models\FuelVariant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FuelStationResourceTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_USER,
            'app_authentication_secret' => 'enrolled',
        ]);
    }

    public function test_a_user_can_create_and_rename_a_fuel_station(): void
    {
        $this->actingAs($this->user());

        Livewire::test(CreateFuelStation::class)
            ->fillForm(['name' => '  NIS  Petrol '])
            ->call('create')
            ->assertHasNoFormErrors();

        $station = FuelStation::query()->firstOrFail();
        $this->assertSame('NIS Petrol', $station->name);
        $this->assertSame('nis petrol', $station->name_key);

        Livewire::test(CreateFuelStation::class)
            ->fillForm(['name' => 'nis petrol'])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $entry = FuelEntry::factory()->create([
            'gas_station' => 'NIS Petrol',
            'fuel_variant' => 'B7',
        ]);

        Livewire::test(EditFuelStation::class, ['record' => $station->id])
            ->fillForm(['name' => 'NIS'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('NIS', $entry->refresh()->gas_station);
    }

    public function test_variants_are_managed_on_the_station(): void
    {
        $station = FuelStation::factory()->create(['name' => 'OMV']);
        $other = FuelStation::factory()->create(['name' => 'NIS']);
        FuelVariant::factory()->create([
            'fuel_station_id' => $other->id,
            'name' => '95',
        ]);
        $this->actingAs($this->user());

        Livewire::test(FuelVariantsRelationManager::class, [
            'ownerRecord' => $station,
            'pageClass' => EditFuelStation::class,
        ])
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => '95',
            ])
            ->assertHasNoActionErrors();

        $variant = $station->variants()->firstOrFail();
        $this->assertSame('95', $variant->name);

        Livewire::test(FuelVariantsRelationManager::class, [
            'ownerRecord' => $station,
            'pageClass' => EditFuelStation::class,
        ])
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => '95',
            ])
            ->assertHasActionErrors(['name']);

        $this->assertSame(1, $station->variants()->count());
    }

    public function test_resolve_from_fuel_entries_classifies_mismatched_names(): void
    {
        $placeholder = FuelStation::factory()->create(['name' => 'Placeholder']);
        $carId = Car::factory()->create()->id;
        $userId = User::factory()->create()->id;

        DB::table('fuel_entries')->insert([
            'car_id' => $carId,
            'user_id' => $userId,
            'fuel_station_id' => $placeholder->id,
            'gas_station' => 'MOL',
            'fuel_variant' => '95',
            'litres_filled' => 20,
            'amount' => 3000,
            'odometer_at_fill' => 1000,
            'date_of_filling' => now(),
            'is_partial_fill' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user());

        Livewire::test(ListFuelStations::class)
            ->callAction('resolveFromFuelEntries')
            ->assertNotified('Fuel entries classified');

        $entry = FuelEntry::query()->firstOrFail();
        $this->assertSame('MOL', $entry->gas_station);
        $this->assertSame('95', $entry->fuel_variant);
        $this->assertNotSame($placeholder->id, $entry->fuel_station_id);

        Livewire::test(ListFuelStations::class)
            ->callAction('resolveFromFuelEntries')
            ->assertNotified('Fuel entries classified');

        $this->assertSame(2, FuelStation::query()->count());
        $this->assertSame(1, FuelVariant::query()->count());
    }

    public function test_a_variant_can_be_merged_into_a_similar_one_from_the_station_page(): void
    {
        $station = FuelStation::factory()->create(['name' => 'OMV']);
        $keep = FuelVariant::factory()->create([
            'fuel_station_id' => $station->id,
            'name' => '95',
        ]);
        $alias = FuelVariant::factory()->create([
            'fuel_station_id' => $station->id,
            'name' => 'Euro 95',
        ]);
        $unrelated = FuelVariant::factory()->create([
            'fuel_station_id' => $station->id,
            'name' => '98',
        ]);
        $entry = FuelEntry::factory()->create([
            'gas_station' => 'OMV',
            'fuel_variant' => 'Euro 95',
        ]);
        $this->actingAs($this->user());

        $manager = Livewire::test(FuelVariantsRelationManager::class, [
            'ownerRecord' => $station,
            'pageClass' => EditFuelStation::class,
        ]);

        $manager->assertActionHidden(TestAction::make('merge')->table($unrelated));

        $manager
            ->callAction(TestAction::make('merge')->table($alias), data: [
                'target_id' => $keep->id,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Variants merged');

        $entry->refresh();
        $this->assertSame($keep->id, $entry->fuel_variant_id);
        $this->assertSame('95', $entry->fuel_variant);
        $this->assertModelMissing($alias);
        $this->assertModelExists($unrelated);
    }
}
