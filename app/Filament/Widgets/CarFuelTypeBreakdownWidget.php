<?php

namespace App\Filament\Widgets;

use App\Models\Car;
use App\Services\AnalyticsService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CarFuelTypeBreakdownWidget extends TableWidget
{
    public Car $car;

    public function mount(Car $car): void
    {
        $this->car = $car;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Spend/efficiency by fuel type')
            ->records(function (): array {
                $entries = $this->car->fuelEntries()->with(['user', 'car'])->get();
                $summary = app(AnalyticsService::class)->allTimeSummaryForCar($entries);

                return $summary->byFuelType
                    ->values()
                    ->map(fn ($fuelType, int $index): array => [
                        'id' => $index,
                        'label' => $fuelType->label(),
                        'total_amount' => $fuelType->totalAmount,
                        'total_litres' => $fuelType->totalLitres,
                        'average_l100km' => $fuelType->averageL100km,
                        'fill_up_count' => $fuelType->fillUpCount,
                    ])
                    ->all();
            })
            ->columns([
                TextColumn::make('label')
                    ->label('Fuel type'),
                TextColumn::make('total_amount')
                    ->label('Amount (RSD)')
                    ->numeric(2),
                TextColumn::make('total_litres')
                    ->label('Litres')
                    ->numeric(3),
                TextColumn::make('average_l100km')
                    ->label('Avg L/100km')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : number_format($state, 2, ',', '.')),
                TextColumn::make('fill_up_count')
                    ->label('Fill-ups'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No fuel entries for this car yet.');
    }
}
