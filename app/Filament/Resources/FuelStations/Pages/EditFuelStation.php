<?php

namespace App\Filament\Resources\FuelStations\Pages;

use App\Filament\Resources\FuelStations\FuelStationResource;
use App\Models\FuelStation;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFuelStation extends EditRecord
{
    protected static string $resource = FuelStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (FuelStation $record): bool => auth()->user()->can('delete', $record)),
        ];
    }
}
