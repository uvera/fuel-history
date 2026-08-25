<?php

namespace App\Models;

use Database\Factories\FuelEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'car_id',
    'user_id',
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
     * @return array<string, array<int, string>>
     */
    public static function validationRules(bool $allFieldsRequired = true): array
    {
        $required = $allFieldsRequired ? 'required' : 'sometimes';

        return [
            'car_id' => [$required, 'integer', 'exists:cars,id'],
            'gas_station' => [$required, 'string', 'max:255'],
            'litres_filled' => [$required, 'numeric', 'min:0.001'],
            'amount' => [$required, 'numeric', 'min:0.01'],
            'odometer_at_fill' => [$required, 'integer', 'min:0'],
            'date_of_filling' => [$required, 'date'],
            'is_partial_fill' => ['sometimes', 'boolean'],
            'trip_km' => ['nullable', 'numeric', 'min:0.1'],
        ];
    }
}
