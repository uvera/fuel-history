<?php

namespace App\Models;

use App\Services\FuelCatalog;
use Database\Factories\FuelVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fuel_station_id', 'name', 'name_key'])]
class FuelVariant extends Model
{
    /** @use HasFactory<FuelVariantFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (FuelVariant $variant): void {
            $variant->name = FuelCatalog::displayName((string) $variant->name);
            $variant->name_key = FuelCatalog::nameKey($variant->name);
        });

        static::saved(function (FuelVariant $variant): void {
            if (! $variant->wasChanged('name')) {
                return;
            }

            $variant->fuelEntries()->update(['fuel_variant' => $variant->name]);
        });
    }

    public function station()
    {
        return $this->belongsTo(FuelStation::class, 'fuel_station_id');
    }

    public function fuelEntries()
    {
        return $this->hasMany(FuelEntry::class);
    }
}
