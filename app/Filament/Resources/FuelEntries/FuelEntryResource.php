<?php

namespace App\Filament\Resources\FuelEntries;

use App\Filament\Resources\FuelEntries\Pages\CreateFuelEntry;
use App\Filament\Resources\FuelEntries\Pages\EditFuelEntry;
use App\Filament\Resources\FuelEntries\Pages\ListFuelEntries;
use App\Filament\Resources\FuelEntries\Schemas\FuelEntryForm;
use App\Filament\Resources\FuelEntries\Tables\FuelEntriesTable;
use App\Models\FuelEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FuelEntryResource extends Resource
{
    protected static ?string $model = FuelEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FuelEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FuelEntriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFuelEntries::route('/'),
            'create' => CreateFuelEntry::route('/create'),
            'edit' => EditFuelEntry::route('/{record}/edit'),
        ];
    }
}
