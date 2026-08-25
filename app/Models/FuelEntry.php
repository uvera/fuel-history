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
}
