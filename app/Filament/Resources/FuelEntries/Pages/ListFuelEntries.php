<?php

namespace App\Filament\Resources\FuelEntries\Pages;

use App\Filament\Resources\FuelEntries\FuelEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFuelEntries extends ListRecords
{
    protected static string $resource = FuelEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
