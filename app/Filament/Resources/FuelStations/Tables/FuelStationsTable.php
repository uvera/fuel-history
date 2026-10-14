<?php

namespace App\Filament\Resources\FuelStations\Tables;

use App\Filament\Resources\FuelStations\FuelStationResource;
use App\Filament\Resources\FuelStations\Pages\EditFuelStation;
use App\Models\FuelStation;
use App\Services\Data\FuelStationMergeResult;
use App\Services\FuelCatalog;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Component;

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
                self::mergeAction(),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (FuelStation $record): bool => auth()->user()->can('delete', $record)),
            ]);
    }

    public static function mergeAction(): Action
    {
        return Action::make('merge')
            ->label('Merge')
            ->modalHeading('Merge fuel stations')
            ->modalDescription('Variants move to the station you keep. Variants with the same name are combined so fills point at one variant. This station is removed.')
            ->visible(fn (FuelStation $record): bool => FuelStation::query()->whereKeyNot($record->getKey())->exists())
            ->schema([
                Select::make('target_id')
                    ->label('Keep')
                    ->options(fn (FuelStation $record): array => FuelStation::query()
                        ->whereKeyNot($record->getKey())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(function (FuelStation $record, array $data, Component $livewire): void {
                $target = FuelStation::query()->findOrFail($data['target_id']);
                $result = app(FuelCatalog::class)->mergeStations($record, $target);

                Notification::make()
                    ->title('Stations merged')
                    ->body(self::mergeBody($target, $result))
                    ->success()
                    ->send();

                if ($livewire instanceof EditFuelStation) {
                    $livewire->redirect(FuelStationResource::getUrl('edit', ['record' => $target]));
                }
            });
    }

    private static function mergeBody(FuelStation $target, FuelStationMergeResult $result): string
    {
        $combined = $result->variantsCombined > 0
            ? " {$result->variantsCombined} variants with the same name were combined."
            : '';

        return "{$result->entriesMoved} fuel entries now use {$target->name}. Kept {$result->variantsMoved} variants.{$combined}";
    }
}
