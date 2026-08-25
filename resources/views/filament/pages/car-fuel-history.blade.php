<x-filament-panels::page>
    @livewire(\App\Filament\Widgets\CarFuelSummaryStatsWidget::class, ['car' => $record])

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        @livewire(\App\Filament\Widgets\CarStationBreakdownWidget::class, ['car' => $record])
        @livewire(\App\Filament\Widgets\CarFuelTypeBreakdownWidget::class, ['car' => $record])
    </div>

    @livewire(\App\Filament\Widgets\CarAnalyticsWidget::class, ['car' => $record])

    {{ $this->table }}
</x-filament-panels::page>
