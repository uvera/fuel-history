<?php

namespace App\Filament\Resources\Cars\Tables;

use App\Filament\Pages\CarFuelHistory;
use App\Models\Car;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('make')
                    ->searchable(),
                TextColumn::make('model')
                    ->searchable(),
                TextColumn::make('year'),
                TextColumn::make('plate')
                    ->label('Plate'),
                TextColumn::make('fuel_type')
                    ->label('Fuel type')
                    ->badge(),
                IconColumn::make('archived_at')
                    ->label('Archived')
                    ->boolean()
                    ->getStateUsing(fn (Car $record): bool => $record->isArchived()),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('archived')
                    ->label('Archived')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('archived_at'),
                        false: fn ($query) => $query->whereNull('archived_at'),
                        blank: fn ($query) => $query,
                    )
                    ->default(false),
            ])
            ->recordActions([
                Action::make('fuelHistory')
                    ->label('Fuel history')
                    ->icon('heroicon-o-table-cells')
                    ->url(fn (Car $record) => CarFuelHistory::getUrl(['record' => $record])),
                EditAction::make()
                    ->visible(fn (Car $record) => auth()->user()->can('update', $record)),
                Action::make('archive')
                    ->label('Archive')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Car $record) => (! $record->isArchived()) && auth()->user()->can('update', $record))
                    ->action(fn (Car $record) => $record->archive()),
                Action::make('unarchive')
                    ->label('Unarchive')
                    ->color('success')
                    ->visible(fn (Car $record) => $record->isArchived() && auth()->user()->can('update', $record))
                    ->action(fn (Car $record) => $record->unarchive()),
            ]);
        // No delete action anywhere — cars are archived, never hard-deleted
        // (see CarPolicy::delete, which always returns false).
    }
}
