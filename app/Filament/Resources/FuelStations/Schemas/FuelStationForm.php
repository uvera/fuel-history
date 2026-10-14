<?php

namespace App\Filament\Resources\FuelStations\Schemas;

use App\Models\FuelStation;
use App\Services\FuelCatalog;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FuelStationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->rule(function (?FuelStation $record): Closure {
                        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $name = (string) $value;

                            if (FuelCatalog::displayName($name) === '') {
                                $fail('The name field is required.');

                                return;
                            }

                            if (! app(FuelCatalog::class)->stationNameIsAvailable($name, $record?->getKey())) {
                                $fail('A fuel station with this name already exists.');
                            }
                        };
                    }),
            ]);
    }
}
