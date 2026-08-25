<?php

namespace App\Filament\Resources\FuelEntries\Tables;

use App\Models\FuelEntry;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FuelEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date_of_filling')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('car.name')
                    ->label('Car')
                    ->sortable(),
                TextColumn::make('fuel_type')
                    ->label('Fuel')
                    ->badge(),
                TextColumn::make('fuel_variant')
                    ->label('Variant')
                    ->placeholder('—'),
                TextColumn::make('gas_station')
                    ->searchable(),
                TextColumn::make('litres_filled')
                    ->numeric(3)
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Amount (RSD)')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('price_per_litre')
                    ->label('Price/L')
                    ->state(fn (FuelEntry $record): string => number_format(
                        $record->litres_filled > 0 ? $record->amount / $record->litres_filled : 0,
                        2
                    )),
                TextColumn::make('odometer_at_fill')
                    ->label('Odometer')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_partial_fill')
                    ->label('Partial')
                    ->boolean(),
                TextColumn::make('user.name')
                    ->label('Logged by'),
            ])
            ->defaultSort('date_of_filling', 'desc')
            ->recordActions([
                EditAction::make()
                    ->visible(fn (FuelEntry $record) => auth()->user()->can('update', $record)),
                DeleteAction::make()
                    ->visible(fn (FuelEntry $record) => auth()->user()->can('delete', $record)),
            ]);
    }
}
