<?php

namespace App\Filament\Resources\FuelEntries\Pages;

use App\Filament\Resources\FuelEntries\FuelEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFuelEntry extends EditRecord
{
    protected static string $resource = FuelEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
