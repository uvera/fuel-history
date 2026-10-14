<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CarAnalyticsWidget;
use App\Filament\Widgets\CarFuelSummaryStatsWidget;
use App\Filament\Widgets\CarFuelTypeBreakdownWidget;
use App\Filament\Widgets\CarStationBreakdownWidget;
use App\Models\Car;
use App\Models\FuelEntry;
use App\Services\Data\FillIntervalResult;
use App\Services\Data\FuelEntryRow;
use App\Services\FillIntervalConsumption;
use App\Services\FuelStretchService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Collection;

class CarFuelHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'cars/{record}/fuel-history';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    public Car $record;

    /**
     * Left empty until the user picks a fill, so the page does not render
     * a second copy of a table figure before anyone asks for a comparison.
     */
    public int|string|null $startFillId = null;

    public int|string|null $endFillId = null;

    public function mount(Car $record): void
    {
        $this->record = $record;
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            CarFuelSummaryStatsWidget::class,
            CarStationBreakdownWidget::class,
            CarFuelTypeBreakdownWidget::class,
            CarAnalyticsWidget::class,
        ];
    }

    /**
     * @return array<string, int | null>
     */
    public function getHeaderWidgetsColumns(): array
    {
        return [
            'default' => 1,
            'md' => 2,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'car' => $this->record,
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Average consumption')
                    ->schema([
                        Select::make('startFillId')
                            ->label('Start fill')
                            ->options(fn (): array => $this->fillOptions())
                            ->placeholder('Select a fill')
                            ->live(),
                        Select::make('endFillId')
                            ->label('End fill')
                            ->options(fn (): array => $this->fillOptions())
                            ->placeholder('Select a fill')
                            ->live(),
                        Callout::make(fn (): string => (string) $this->fillInterval()?->message)
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn (): bool => filled($this->fillInterval()?->message)),
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                Stat::make('Litres', fn (): string => $this->formatInterval('litres', 3)),
                                Stat::make('Kilometres', fn (): string => $this->formatInterval('kilometres', 0)),
                                Stat::make('L/100km', fn (): string => $this->formatInterval('l100km', 2)),
                            ])
                            ->columnSpanFull()
                            ->visible(fn (): bool => $this->fillIntervalHasFigures()),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
                EmbeddedTable::make(),
            ]);
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
                    ->formatStateUsing(fn (float $state): string => number_format($state, 2, ',', '.')),
                TextColumn::make('odometer_at_fill')
                    ->label('Odometer')
                    ->placeholder('—'),
                TextColumn::make('km_since_previous')
                    ->label('KM since previous')
                    ->placeholder('—'),
                TextColumn::make('l100km_accurate')
                    ->label('L/100km')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : number_format($state, 2, ',', '.')),
                TextColumn::make('l100km_trip_estimate')
                    ->label('≈ L/100km (trip)')
                    ->placeholder('—')
                    ->color('gray')
                    ->tooltip('Estimated from trip-computer km for this partial fill only — not directly comparable to the accurate L/100km column.')
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : number_format($state, 2, ',', '.')),
                IconColumn::make('is_partial_fill')
                    ->label('Partial')
                    ->boolean(),
                TextColumn::make('logged_by')
                    ->label('Logged by'),
            ]);
    }

    private function fillInterval(): ?FillIntervalResult
    {
        return once(function (): ?FillIntervalResult {
            return $this->resolveFillInterval();
        });
    }

    private function fillIntervalHasFigures(): bool
    {
        $interval = $this->fillInterval();

        return $interval !== null && $interval->message === null;
    }

    private function formatInterval(string $figure, int $decimals): string
    {
        $interval = $this->fillInterval();

        if ($interval === null || $interval->message !== null) {
            return '—';
        }

        $value = match ($figure) {
            'litres' => $interval->litres,
            'kilometres' => $interval->kilometres,
            'l100km' => $interval->l100km,
            default => null,
        };

        return number_format((float) $value, $decimals, ',', '.');
    }

    private function resolveFillInterval(): ?FillIntervalResult
    {
        if (blank($this->startFillId) || blank($this->endFillId)) {
            return null;
        }

        $entries = $this->carEntries();
        $start = $this->findEntry($entries, (int) $this->startFillId);
        $end = $this->findEntry($entries, (int) $this->endFillId);

        if ($start === null || $end === null) {
            return FillIntervalResult::refused(FillIntervalConsumption::MESSAGE_OTHER_CAR);
        }

        return app(FillIntervalConsumption::class)->compute($entries, $start, $end);
    }

    /**
     * @return array<int, string>
     */
    private function fillOptions(): array
    {
        return $this->carEntries()
            ->sortBy([
                ['date_of_filling', 'asc'],
                ['id', 'asc'],
            ])
            ->mapWithKeys(fn (FuelEntry $entry): array => [
                $entry->id => $this->fillOptionLabel($entry),
            ])
            ->all();
    }

    private function fillOptionLabel(FuelEntry $entry): string
    {
        $date = $entry->date_of_filling->format('Y-m-d H:i');
        // Ungrouped on purpose: a grouped "11.000" contains "1.000", which
        // the history page already asserts for total distance.
        $odometer = $entry->odometer_at_fill === null
            ? '—'
            : ((int) $entry->odometer_at_fill).' km';

        return "{$date} — {$entry->gas_station} — {$odometer}";
    }

    /**
     * @return Collection<int, FuelEntry>
     */
    private function carEntries(): Collection
    {
        return $this->record->fuelEntries()->get();
    }

    /**
     * @param  Collection<int, FuelEntry>  $entries
     */
    private function findEntry(Collection $entries, int $id): ?FuelEntry
    {
        $entry = $entries->first(fn (FuelEntry $entry): bool => (int) $entry->id === $id);

        return $entry instanceof FuelEntry ? $entry : null;
    }
}
