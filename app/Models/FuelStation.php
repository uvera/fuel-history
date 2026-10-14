<?php

namespace App\Models;

use App\Services\FuelCatalog;
use Database\Factories\FuelStationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'name_key'])]
class FuelStation extends Model
{
    /** @use HasFactory<FuelStationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (FuelStation $station): void {
            $station->name = FuelCatalog::displayName((string) $station->name);
            $station->name_key = FuelCatalog::nameKey($station->name);
        });

        static::saved(function (FuelStation $station): void {
            if (! $station->wasChanged('name')) {
                return;
            }

            $station->fuelEntries()->update(['gas_station' => $station->name]);
        });

        static::deleting(function (FuelStation $station): ?bool {
            if ($station->fuelEntries()->exists()) {
                return false;
            }

            $station->variants()->delete();

            return null;
        });
    }

    public function variants()
    {
        return $this->hasMany(FuelVariant::class);
    }

    public function fuelEntries()
    {
        return $this->hasMany(FuelEntry::class);
    }
}
