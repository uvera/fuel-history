<?php

namespace App\Filament\Widgets;

use App\Models\Car;
use App\Services\AnalyticsPeriod;
use App\Services\AnalyticsService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CarAnalyticsWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public Car $car;

    public function mount(Car $car): void
    {
        $this->car = $car;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Analytics')
            ->filters([
                SelectFilter::make('period')
                    ->label('Period')
                    ->options([
                        'month' => 'Monthly',
                        'quarter' => 'Quarterly',
                        'year' => 'Yearly',
                    ])
                    ->default('month')
                    ->selectablePlaceholder(false),
            ])
            ->records(function (): array {
                $period = AnalyticsPeriod::from($this->getTableFilterState('period')['value'] ?? 'month');
                $entries = $this->car->fuelEntries()->with('car')->get();
                $buckets = app(AnalyticsService::class)->bucketsForCar($entries, $period);

                return $buckets
                    ->reverse()
                    ->values()
                    ->map(fn ($bucket, int $index): array => [
                        'id' => $index,
                        'label' => $bucket->label,
                        'amount' => $bucket->totalAmount,
                        'litres' => $bucket->totalLitres,
                        'avg_l100km' => $bucket->averageL100km(),
                    ])
                    ->all();
            })
            ->columns([
                TextColumn::make('label')
                    ->label('Period'),
                TextColumn::make('amount')
                    ->label('Spend (RSD)')
                    ->numeric(2),
                TextColumn::make('litres')
                    ->label('Litres')
                    ->numeric(3),
                TextColumn::make('avg_l100km')
                    ->label('Avg L/100km')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : number_format($state, 2, ',', '.')),
            ])
            ->paginated(false)
            ->emptyStateHeading('No fuel entries for this car yet.');
    }
}
