<?php

namespace App\Models;

use App\Enums\FuelType;
use Database\Factories\FuelEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

#[Fillable([
    'car_id',
    'user_id',
    'fuel_type',
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
            'fuel_type' => FuelType::class,
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

    /**
     * Shared validation constraints for every entry point that creates or
     * updates a fuel entry outside the Filament form's own fluent field
     * rules (currently just the MCP tools) — kept in one place so the
     * create and edit tools can't drift apart from each other. These
     * mirror the DB-level CHECK constraints in the fuel_entries migration
     * (which remain the actual last line of defense regardless of which
     * entry point is used), plus the fields those constraints can't
     * express (required-ness, foreign key existence).
     *
     * fuel_type/fuel_variant are deliberately NOT backed by a DB
     * constraint (existing rows have no sane default) — required-ness for
     * them lives here only. fuel_variant is required unless fuel_type is
     * Electric; on edit (allFieldsRequired=false) both stay optional like
     * every other partial-update field, so a caller can still patch just
     * fuel_type without being forced to also resend fuel_variant.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(bool $allFieldsRequired = true): array
    {
        $required = $allFieldsRequired ? 'required' : 'sometimes';

        return [
            'car_id' => [$required, 'integer', 'exists:cars,id'],
            'fuel_type' => [$required, Rule::enum(FuelType::class)],
            'fuel_variant' => $allFieldsRequired
                ? ['nullable', 'required_unless:fuel_type,'.FuelType::Electric->value, 'string', 'max:255']
                : ['sometimes', 'nullable', 'string', 'max:255'],
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
