<?php

namespace Tests\Feature;

use App\Enums\FuelType;
use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\FuelStation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FuelEntryValidationRulesTest extends TestCase
{
    use RefreshDatabase;

    private function baseData(Car $car): array
    {
        return [
            'car_id' => $car->id,
            'gas_station' => 'Test Station',
            'litres_filled' => 40,
            'amount' => 5000,
            'odometer_at_fill' => 1000,
            'date_of_filling' => now(),
        ];
    }

    public function test_fuel_variant_is_nullable_and_fuel_type_lives_on_the_car(): void
    {
        $this->assertFalse(Schema::hasColumn('fuel_entries', 'fuel_type'));
        $this->assertTrue(Schema::hasColumn('fuel_entries', 'fuel_variant'));
        $this->assertTrue(Schema::hasColumn('cars', 'fuel_type'));

        $stationColumn = collect(Schema::getColumns('fuel_entries'))->firstWhere('name', 'fuel_station_id');
        $this->assertFalse($stationColumn['nullable']);
        $variantColumn = collect(Schema::getColumns('fuel_entries'))->firstWhere('name', 'fuel_variant_id');
        $this->assertTrue($variantColumn['nullable']);

        $foreignKeys = collect(Schema::getForeignKeys('fuel_entries'));
        $stationKey = $foreignKeys->first(fn (array $key): bool => in_array('fuel_station_id', $key['columns'], true));
        $variantKey = $foreignKeys->first(fn (array $key): bool => in_array('fuel_variant_id', $key['columns'], true));
        $this->assertNotNull($stationKey);
        $this->assertNotNull($variantKey);
        $this->assertSame('restrict', $stationKey['on_delete']);
        $this->assertSame('restrict', $variantKey['on_delete']);

        $column = collect(Schema::getColumns('cars'))->firstWhere('name', 'fuel_type');
        $this->assertFalse($column['nullable']);
        $this->assertNull($column['default']);

        $car = Car::factory()->create();
        $userId = User::factory()->create()->id;
        $station = FuelStation::factory()->create(['name' => 'Legacy Station']);

        // Raw insert bypassing the model/validation layer entirely --
        // proves the DB itself does not enforce NOT NULL on fuel_variant.
        DB::table('fuel_entries')->insert([
            'car_id' => $car->id,
            'user_id' => $userId,
            'fuel_station_id' => $station->id,
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

        $this->assertDatabaseHas('fuel_entries', [
            'gas_station' => 'Legacy Station',
            'fuel_variant' => null,
        ]);

        try {
            DB::table('cars')->insert([
                'name' => 'No Fuel Type',
                'fuel_type' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('A car without a fuel type should be rejected by the database.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function test_create_validation_does_not_accept_a_fuel_type_on_the_fill(): void
    {
        $this->assertArrayNotHasKey('fuel_type', FuelEntry::validationRules());
    }

    public function test_create_validation_requires_fuel_variant_when_the_car_is_not_electric(): void
    {
        $car = Car::factory()->create(['fuel_type' => FuelType::Gasoline]);

        $validator = Validator::make(
            $this->baseData($car),
            FuelEntry::validationRules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('fuel_variant', $validator->errors()->toArray());
    }

    public function test_create_validation_allows_missing_fuel_variant_when_the_car_is_electric(): void
    {
        $car = Car::factory()->create(['fuel_type' => FuelType::Electric]);

        $validator = Validator::make(
            $this->baseData($car),
            FuelEntry::validationRules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_create_validation_allows_explicit_null_fuel_variant_when_the_car_is_electric(): void
    {
        $car = Car::factory()->create(['fuel_type' => FuelType::Electric]);

        $validator = Validator::make(
            [...$this->baseData($car), 'fuel_variant' => null],
            FuelEntry::validationRules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_create_validation_rejects_explicit_null_fuel_variant_when_the_car_is_not_electric(): void
    {
        $car = Car::factory()->create(['fuel_type' => FuelType::Diesel]);

        $validator = Validator::make(
            [...$this->baseData($car), 'fuel_variant' => null],
            FuelEntry::validationRules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('fuel_variant', $validator->errors()->toArray());
    }

    public function test_edit_validation_allows_omitting_fuel_variant_entirely(): void
    {
        $validator = Validator::make(
            ['gas_station' => 'Updated Station'],
            FuelEntry::validationRules(allFieldsRequired: false),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_effective_state_requires_a_variant_when_the_car_is_not_electric(): void
    {
        $gasoline = Car::factory()->create(['fuel_type' => FuelType::Gasoline]);
        $electric = Car::factory()->create(['fuel_type' => FuelType::Electric]);

        $missingOnGasoline = Validator::make(
            ['car_id' => $gasoline->id, 'fuel_variant' => null],
            Arr::only(FuelEntry::validationRules(), ['fuel_variant']),
        );
        $this->assertTrue($missingOnGasoline->fails());
        $this->assertArrayHasKey('fuel_variant', $missingOnGasoline->errors()->toArray());

        $missingOnElectric = Validator::make(
            ['car_id' => $electric->id, 'fuel_variant' => null],
            Arr::only(FuelEntry::validationRules(), ['fuel_variant']),
        );
        $this->assertFalse($missingOnElectric->fails());

        $keptVariant = Validator::make(
            ['car_id' => $gasoline->id, 'fuel_variant' => '95'],
            Arr::only(FuelEntry::validationRules(), ['fuel_variant']),
        );
        $this->assertFalse($keptVariant->fails());
    }
}
