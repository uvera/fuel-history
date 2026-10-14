<?php

namespace App\Models;

use App\Enums\FuelType;
use Database\Factories\CarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'make', 'model', 'year', 'plate', 'fuel_type'])]
class Car extends Model
{
    /** @use HasFactory<CarFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'archived_at' => 'datetime',
            'fuel_type' => FuelType::class,
        ];
    }

    public function fuelEntries()
    {
        return $this->hasMany(FuelEntry::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(): void
    {
        $this->archived_at = now();
        $this->save();
    }

    public function unarchive(): void
    {
        $this->archived_at = null;
        $this->save();
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
