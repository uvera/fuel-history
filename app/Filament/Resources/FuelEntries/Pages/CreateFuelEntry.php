<?php

namespace App\Filament\Resources\FuelEntries\Pages;

use App\Filament\Resources\FuelEntries\FuelEntryResource;
use App\Services\FuelCatalog;
use Filament\Resources\Pages\CreateRecord;

class CreateFuelEntry extends CreateRecord
{
    protected static string $resource = FuelEntryResource::class;

    /**
     * The logged-in user is always the entry's owner — never a form
     * field, never trusted from client input. Station and variant names
     * are copied from the catalog rows the form selected.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return app(FuelCatalog::class)->withCanonicalNames($data);
    }
}
