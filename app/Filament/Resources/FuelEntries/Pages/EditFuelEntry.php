<?php

namespace App\Filament\Resources\FuelEntries\Pages;

use App\Filament\Resources\FuelEntries\FuelEntryResource;
use App\Services\FuelCatalog;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(FuelCatalog::class)->withCanonicalNames($data);
    }
}
