<?php

namespace App\Filament\Resources\FuelStations\Tables;

use App\Models\FuelStation;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FuelStationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('Variants'),
                TextColumn::make('fuel_entries_count')
                    ->counts('fuelEntries')
                    ->label('Fuel entries'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (FuelStation $record): bool => auth()->user()->can('delete', $record)),
            ]);
    }
}
