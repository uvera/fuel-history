<?php

namespace App\Filament\Widgets;

use App\Models\Car;
use App\Services\AnalyticsService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CarStationBreakdownWidget extends TableWidget
{
    public Car $car;

    public function mount(Car $car): void
    {
        $this->car = $car;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Spend by station')
            ->records(function (): array {
                $entries = $this->car->fuelEntries()->with(['user', 'car'])->get();
                $summary = app(AnalyticsService::class)->allTimeSummaryForCar($entries);

                return $summary->byStation
                    ->values()
                    ->map(fn ($station, int $index): array => [
                        'id' => $index,
                        'gas_station' => $station->gasStation,
                        'total_amount' => $station->totalAmount,
                        'total_litres' => $station->totalLitres,
                        'fill_up_count' => $station->fillUpCount,
                    ])
                    ->all();
            })
            ->columns([
                TextColumn::make('gas_station')
                    ->label('Station'),
                TextColumn::make('total_amount')
                    ->label('Amount (RSD)')
                    ->numeric(2),
                TextColumn::make('total_litres')
                    ->label('Litres')
                    ->numeric(3),
                TextColumn::make('fill_up_count')
                    ->label('Fill-ups'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No fuel entries for this car yet.');
    }
}
