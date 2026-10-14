<?php

namespace App\Filament\Resources\FuelEntries\Schemas;

use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\FuelStation;
use App\Models\FuelVariant;
use App\Services\FuelCatalog;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class FuelEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('car_id')
                    ->label('Car')
                    ->options(fn () => Car::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live(),
                Select::make('fuel_station_id')
                    ->label('Gas station')
                    ->relationship('fuelStation', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                        $variantId = $get('fuel_variant_id');

                        if (blank($variantId)) {
                            return;
                        }

                        if (blank($state)) {
                            $set('fuel_variant_id', null);

                            return;
                        }

                        $belongsToStation = FuelVariant::query()
                            ->whereKey($variantId)
                            ->where('fuel_station_id', $state)
                            ->exists();

                        if (! $belongsToStation) {
                            $set('fuel_variant_id', null);
                        }
                    })
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Station name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        return app(FuelCatalog::class)->findOrCreateStation($data['name'])->getKey();
                    }),
                Select::make('fuel_variant_id')
                    ->label('Fuel variant')
                    ->helperText('The specific grade or product, e.g. "95", "B7", "Shell V-Power".')
                    ->relationship(
                        name: 'fuelVariant',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('fuel_station_id', $get('fuel_station_id')),
                    )
                    ->searchable()
                    ->preload()
                    ->disabled(fn (Get $get): bool => blank($get('fuel_station_id')))
                    ->required(function (Get $get): bool {
                        $carId = $get('car_id');

                        if (blank($carId)) {
                            return true;
                        }

                        return FuelEntry::fuelVariantIsRequired(
                            Car::query()->find($carId)?->fuel_type,
                        );
                    })
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Variant')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(function (array $data, Get $get): int {
                        $station = FuelStation::query()->findOrFail($get('fuel_station_id'));

                        return app(FuelCatalog::class)->findOrCreateVariant($station, $data['name'])->getKey();
                    }),
                TextInput::make('litres_filled')
                    ->label('Litres filled')
                    ->numeric()
                    ->minValue(0.001)
                    ->step(0.001)
                    ->required(),
                TextInput::make('amount')
                    ->label('Amount (RSD)')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required(),
                TextInput::make('odometer_at_fill')
                    ->label('Odometer (km)')
                    ->helperText('Leave blank if you only have the receipt, with no odometer photo.')
                    ->numeric()
                    ->minValue(0),
                DateTimePicker::make('date_of_filling')
                    ->required()
                    ->default(now()),
                Checkbox::make('is_partial_fill')
                    ->label('Partial fill (tank not filled to full)')
                    ->live(),
                TextInput::make('trip_km')
                    ->label('Trip kilometres since last fill (optional)')
                    ->helperText('From the car\'s trip computer, if reset at the last fill-up — lets us estimate L/100km for this partial fill alone.')
                    ->numeric()
                    ->minValue(0.1)
                    ->step(0.1)
                    ->visible(fn (Get $get): bool => (bool) $get('is_partial_fill')),
            ]);
    }
}
