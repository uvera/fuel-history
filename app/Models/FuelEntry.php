<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Rules\FuelVariantRequiredUnlessCarIsElectric;
use Database\Factories\FuelEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'car_id',
    'user_id',
    'fuel_station_id',
    'fuel_variant_id',
    'fuel_variant',
    'gas_station',
    'litres_filled',
    'amount',
    'odometer_at_fill',
    'date_of_filling',
    'is_partial_fill',
    'trip_km',
])]
class FuelEntry extends Model
{
    /** @use HasFactory<FuelEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fuel_station_id' => 'integer',
            'fuel_variant_id' => 'integer',
            'litres_filled' => 'decimal:3',
            'amount' => 'decimal:2',
            'odometer_at_fill' => 'integer',
            'date_of_filling' => 'datetime',
            'is_partial_fill' => 'boolean',
            'trip_km' => 'decimal:1',
        ];
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fuelStation()
    {
        return $this->belongsTo(FuelStation::class);
    }

    public function fuelVariant()
    {
        return $this->belongsTo(FuelVariant::class);
    }

    /**
     * Whether a fill must name a fuel variant for this car. Electric cars
     * may omit it. Filament's form and the MCP tools both call this so
     * the exception cannot drift between entry points.
     */
    public static function fuelVariantIsRequired(?FuelType $fuelType): bool
    {
        return $fuelType !== FuelType::Electric;
    }

    /**
     * Shared validation constraints for every entry point that creates or
     * updates a fuel entry outside the Filament form's own fluent field
     * rules (currently just the MCP tools) — kept in one place so the
     * create and edit tools can't drift apart from each other, and so
     * the form can call fuelVariantIsRequired() for the same decision.
     * These mirror the DB-level CHECK constraints in the fuel_entries
     * migration (which remain the actual last line of defense regardless
     * of which entry point is used), plus the fields those constraints
     * can't express (required-ness, foreign key existence).
     *
     * Fuel type lives on the car, not the fill. fuel_variant is required
     * unless that car is electric, resolved from car_id. On edit
     * (allFieldsRequired=false) fuel_variant stays optional in this rule
     * set, like every other partial-update field; the edit tool then
     * re-checks the effective car (sent car_id, or the entry's car) and
     * effective variant against the create rules, including when the car
     * changes. fuel_variant is nullable in the database.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(bool $allFieldsRequired = true): array
    {
        $required = $allFieldsRequired ? 'required' : 'sometimes';

        $fuelVariantRules = ['nullable', 'string', 'max:255', new FuelVariantRequiredUnlessCarIsElectric];

        if (! $allFieldsRequired) {
            array_unshift($fuelVariantRules, 'sometimes');
        }

        return [
            'car_id' => [$required, 'integer', 'exists:cars,id'],
            'fuel_variant' => $fuelVariantRules,
            'gas_station' => [$required, 'string', 'max:255'],
            'litres_filled' => [$required, 'numeric', 'min:0.001'],
            'amount' => [$required, 'numeric', 'min:0.01'],
            // Odometer is the one field that may genuinely be unknown --
            // e.g. a receipt-only entry with no photo of the odometer --
            // so it's always nullable, regardless of $allFieldsRequired.
            'odometer_at_fill' => ['nullable', 'integer', 'min:0'],
            'date_of_filling' => [$required, 'date'],
            'is_partial_fill' => ['sometimes', 'boolean'],
            'trip_km' => ['nullable', 'numeric', 'min:0.1'],
        ];
    }
}
