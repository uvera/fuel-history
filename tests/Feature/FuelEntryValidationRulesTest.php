<?php

namespace Tests\Feature;

use App\Enums\FuelType;
use App\Models\Car;
use App\Models\FuelEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_fuel_type_and_fuel_variant_columns_are_nullable_in_the_database(): void
    {
        $this->assertTrue(Schema::hasColumn('fuel_entries', 'fuel_type'));
        $this->assertTrue(Schema::hasColumn('fuel_entries', 'fuel_variant'));

        $car = Car::factory()->create();
        $userId = \App\Models\User::factory()->create()->id;

        // Raw insert bypassing the model/validation layer entirely --
        // proves the DB itself does not enforce NOT NULL on these columns.
        DB::table('fuel_entries')->insert([
            'car_id' => $car->id,
            'user_id' => $userId,
            'fuel_type' => null,
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
            'fuel_type' => null,
        ]);
    }

    public function test_create_validation_requires_fuel_type(): void
    {
        $car = Car::factory()->create();

        $validator = Validator::make(
            $this->baseData($car),
            FuelEntry::validationRules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('fuel_type', $validator->errors()->toArray());
    }

    public function test_create_validation_requires_fuel_variant_when_fuel_type_is_not_electric(): void
    {
        $car = Car::factory()->create();

        $validator = Validator::make(
            [...$this->baseData($car), 'fuel_type' => FuelType::Gasoline->value],
            FuelEntry::validationRules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('fuel_variant', $validator->errors()->toArray());
    }

    public function test_create_validation_allows_missing_fuel_variant_when_fuel_type_is_electric(): void
    {
        $car = Car::factory()->create();

        $validator = Validator::make(
            [...$this->baseData($car), 'fuel_type' => FuelType::Electric->value],
            FuelEntry::validationRules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_create_validation_allows_explicit_null_fuel_variant_when_fuel_type_is_electric(): void
    {
        $car = Car::factory()->create();

        $validator = Validator::make(
            [...$this->baseData($car), 'fuel_type' => FuelType::Electric->value, 'fuel_variant' => null],
            FuelEntry::validationRules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_create_validation_rejects_explicit_null_fuel_variant_when_fuel_type_is_not_electric(): void
    {
        $car = Car::factory()->create();

        $validator = Validator::make(
            [...$this->baseData($car), 'fuel_type' => FuelType::Diesel->value, 'fuel_variant' => null],
            FuelEntry::validationRules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('fuel_variant', $validator->errors()->toArray());
    }

    public function test_edit_validation_allows_omitting_fuel_type_and_fuel_variant_entirely(): void
    {
        $validator = Validator::make(
            ['gas_station' => 'Updated Station'],
            FuelEntry::validationRules(allFieldsRequired: false),
        );

        $this->assertFalse($validator->fails());
    }
}
