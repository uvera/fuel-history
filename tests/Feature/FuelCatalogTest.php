<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\FuelStation;
use App\Models\FuelVariant;
use App\Models\User;
use App\Services\FuelCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FuelCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_station_matching_ignores_case_and_repeated_spaces_and_keeps_the_first_spelling(): void
    {
        $catalog = app(FuelCatalog::class);

        $first = $catalog->findOrCreateStation('  OMV   Petrol  ');
        $again = $catalog->findOrCreateStation('omv petrol');

        $this->assertSame($first->id, $again->id);
        $this->assertSame('OMV Petrol', $again->name);
        $this->assertSame(1, FuelStation::query()->count());
    }

    public function test_the_same_variant_name_is_a_different_row_at_each_station(): void
    {
        $catalog = app(FuelCatalog::class);
        $omv = $catalog->findOrCreateStation('OMV');
        $nis = $catalog->findOrCreateStation('NIS');

        $omvGrade = $catalog->findOrCreateVariant($omv, '95');
        $nisGrade = $catalog->findOrCreateVariant($nis, '  95 ');
        $again = $catalog->findOrCreateVariant($omv, '95');

        $this->assertNotSame($omvGrade->id, $nisGrade->id);
        $this->assertSame($omvGrade->id, $again->id);
        $this->assertSame('95', $nisGrade->name);
        $this->assertNull($catalog->findOrCreateVariant($omv, '   '));
        $this->assertNull($catalog->findOrCreateVariant($omv, null));
    }

    public function test_renaming_a_station_or_variant_rewrites_linked_entries(): void
    {
        $station = FuelStation::factory()->create(['name' => 'OMV']);
        $variant = FuelVariant::factory()->create([
            'fuel_station_id' => $station->id,
            'name' => '95',
        ]);
        $entry = FuelEntry::factory()->create([
            'gas_station' => 'OMV',
            'fuel_variant' => '95',
        ]);

        $station->update(['name' => 'OMV Petrol']);
        $variant->update(['name' => 'Euro 95']);

        $entry->refresh();
        $this->assertSame('OMV Petrol', $entry->gas_station);
        $this->assertSame('Euro 95', $entry->fuel_variant);
    }

    public function test_resolving_entries_classifies_names_and_a_second_run_creates_nothing(): void
    {
        $carId = Car::factory()->create()->id;
        $userId = User::factory()->create()->id;
        $linkedStation = FuelStation::factory()->create(['name' => 'Already Linked']);

        DB::table('fuel_entries')->insert([
            [
                'car_id' => $carId,
                'user_id' => $userId,
                'fuel_station_id' => $linkedStation->id,
                'gas_station' => 'omv',
                'fuel_variant' => '95',
                'litres_filled' => 10,
                'amount' => 1000,
                'odometer_at_fill' => 100,
                'date_of_filling' => now(),
                'is_partial_fill' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'car_id' => $carId,
                'user_id' => $userId,
                'fuel_station_id' => $linkedStation->id,
                'gas_station' => 'OMV',
                'fuel_variant' => null,
                'litres_filled' => 12,
                'amount' => 1200,
                'odometer_at_fill' => 200,
                'date_of_filling' => now(),
                'is_partial_fill' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $catalog = app(FuelCatalog::class);
        $first = $catalog->resolveFromFuelEntries();

        $this->assertSame(1, $first->stationsCreated);
        $this->assertSame(1, $first->variantsCreated);
        $this->assertSame(2, $first->entriesLinked);

        $entries = FuelEntry::query()->orderBy('id')->get();
        $this->assertSame('omv', $entries[0]->gas_station);
        $this->assertSame('95', $entries[0]->fuel_variant);
        $this->assertSame('omv', $entries[1]->gas_station);
        $this->assertNull($entries[1]->fuel_variant_id);
        $this->assertSame($entries[0]->fuel_station_id, $entries[1]->fuel_station_id);

        $second = $catalog->resolveFromFuelEntries();

        $this->assertSame(0, $second->stationsCreated);
        $this->assertSame(0, $second->variantsCreated);
        $this->assertSame(0, $second->entriesLinked);
        $this->assertSame(2, FuelStation::query()->count());
        $this->assertSame(1, FuelVariant::query()->count());
    }

    public function test_a_station_with_fuel_entries_cannot_be_deleted(): void
    {
        $entry = FuelEntry::factory()->create(['gas_station' => 'OMV']);
        $station = $entry->fuelStation;

        $this->assertFalse($station->delete());
        $this->assertModelExists($station);
    }

    public function test_an_unused_station_can_be_deleted_with_its_variants(): void
    {
        $station = FuelStation::factory()->create(['name' => 'Unused']);
        FuelVariant::factory()->create([
            'fuel_station_id' => $station->id,
            'name' => '95',
        ]);

        $this->assertTrue($station->delete());
        $this->assertSame(0, FuelStation::query()->count());
        $this->assertSame(0, FuelVariant::query()->count());
    }

    public function test_similar_variants_on_one_station_can_be_merged_onto_a_single_variant(): void
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
        $typo = FuelVariant::factory()->create([
            'fuel_station_id' => $station->id,
            'name' => 'Dizel',
        ]);
        $diesel = FuelVariant::factory()->create([
            'fuel_station_id' => $station->id,
            'name' => 'Diesel',
        ]);
        $otherGrade = FuelVariant::factory()->create([
            'fuel_station_id' => $station->id,
            'name' => '98',
        ]);
        $otherStation = FuelVariant::factory()->create(['name' => '95']);
        $entry = FuelEntry::factory()->create([
            'gas_station' => 'OMV',
            'fuel_variant' => 'Euro 95',
        ]);

        $catalog = app(FuelCatalog::class);

        $this->assertTrue(FuelCatalog::variantsAreSimilar($keep, $alias));
        $this->assertTrue(FuelCatalog::variantsAreSimilar($diesel, $typo));
        $this->assertFalse(FuelCatalog::variantsAreSimilar($keep, $otherGrade));
        $this->assertFalse(FuelCatalog::variantsAreSimilar($keep, $otherStation));

        $moved = $catalog->mergeVariants($alias, $keep);

        $this->assertSame(1, $moved);
        $entry->refresh();
        $this->assertSame($keep->id, $entry->fuel_variant_id);
        $this->assertSame('95', $entry->fuel_variant);
        $this->assertModelMissing($alias);

        try {
            $catalog->mergeVariants($otherGrade, $keep);
            $this->fail('Different grades should not merge.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('target_id', $exception->errors());
        }

        $this->assertModelExists($otherGrade);
    }
}
