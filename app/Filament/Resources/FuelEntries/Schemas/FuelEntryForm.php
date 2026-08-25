<?php

namespace App\Filament\Resources\FuelEntries\Schemas;

use App\Models\Car;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

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
                    ->required(),
                TextInput::make('gas_station')
                    ->required()
                    ->maxLength(255),
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
