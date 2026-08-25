<?php

namespace App\Filament\Pages;

use App\Models\Car;
use App\Services\Data\FuelEntryRow;
use App\Services\FuelStretchService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class CarFuelHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'cars/{record}/fuel-history';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.car-fuel-history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    public Car $record;

    public function mount(Car $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return "Fuel history — {$this->record->name}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (): array {
                $entries = $this->record->fuelEntries()->with('user')->get();
                $result = app(FuelStretchService::class)->compute($entries);

                // Filament's records() data source accepts a non-Eloquent
                // query, but each individual record item must still be a
                // Model or plain array (confirmed empirically — passing
                // FuelEntryRow DTOs directly throws a TypeError deep in
                // Filament's record-key resolution). Flatten each row into
                // an array; "id" must be present and unique for Filament to
                // track/key rows correctly.
                return $result->rows
                    ->reverse()
                    ->values()
                    ->map(fn (FuelEntryRow $row): array => [
                        'id' => $row->entry->id,
                        'date_of_filling' => $row->entry->date_of_filling,
                        'gas_station' => $row->entry->gas_station,
                        'litres_filled' => (float) $row->entry->litres_filled,
                        'amount' => (float) $row->entry->amount,
                        'price_per_litre' => $row->pricePerLitre,
                        'odometer_at_fill' => $row->entry->odometer_at_fill,
                        'km_since_previous' => $row->kmSincePrevious,
                        'l100km_accurate' => $row->l100kmAccurate,
                        'l100km_trip_estimate' => $row->l100kmTripEstimate,
                        'is_partial_fill' => $row->entry->is_partial_fill,
                        'logged_by' => $row->entry->user?->name,
                    ])
                    ->all();
            })
            ->columns([
                TextColumn::make('date_of_filling')
                    ->label('Date')
                    ->dateTime(),
                TextColumn::make('gas_station')
                    ->label('Gas station'),
                TextColumn::make('litres_filled')
                    ->label('Litres')
                    ->numeric(3),
                TextColumn::make('amount')
                    ->label('Amount (RSD)')
                    ->numeric(2),
                TextColumn::make('price_per_litre')
                    ->label('Price/L')
                    ->formatStateUsing(fn (float $state): string => number_format($state, 2)),
                TextColumn::make('odometer_at_fill')
                    ->label('Odometer'),
                TextColumn::make('km_since_previous')
                    ->label('KM since previous')
                    ->placeholder('—'),
                TextColumn::make('l100km_accurate')
                    ->label('L/100km')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : number_format($state, 2)),
                TextColumn::make('l100km_trip_estimate')
                    ->label('≈ L/100km (trip)')
                    ->placeholder('—')
                    ->color('gray')
                    ->tooltip('Estimated from trip-computer km for this partial fill only — not directly comparable to the accurate L/100km column.')
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : number_format($state, 2)),
                IconColumn::make('is_partial_fill')
                    ->label('Partial')
                    ->boolean(),
                TextColumn::make('logged_by')
                    ->label('Logged by'),
            ]);
    }
}
