<?php

namespace App\Filament\Resources\FuelStations\Pages;

use App\Filament\Resources\FuelStations\FuelStationResource;
use App\Services\Data\FuelCatalogResolution;
use App\Services\FuelCatalog;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFuelStations extends ListRecords
{
    protected static string $resource = FuelStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resolveFromFuelEntries')
                ->label('Resolve from fuel entries')
                ->requiresConfirmation()
                ->modalDescription('Create fuel stations and variants from the names already stored on fuel entries, and link those entries to the catalog.')
                ->action(function (FuelCatalog $catalog): void {
                    $result = $catalog->resolveFromFuelEntries();

                    Notification::make()
                        ->title('Fuel entries classified')
                        ->body(self::resolutionBody($result))
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }

    private static function resolutionBody(FuelCatalogResolution $result): string
    {
        return "Created {$result->stationsCreated} stations and {$result->variantsCreated} variants. Linked {$result->entriesLinked} entries.";
    }
}
