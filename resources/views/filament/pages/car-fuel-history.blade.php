<x-filament-panels::page>
    @livewire(\App\Filament\Widgets\CarFuelSummaryStatsWidget::class, ['car' => $record])

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        @livewire(\App\Filament\Widgets\CarStationBreakdownWidget::class, ['car' => $record])
        @livewire(\App\Filament\Widgets\CarFuelTypeBreakdownWidget::class, ['car' => $record])
    </div>

    @livewire(\App\Filament\Widgets\CarAnalyticsWidget::class, ['car' => $record])

    <div class="mb-6 space-y-4">
        {{ $this->intervalForm }}

        @if ($interval)
            @if ($interval->message)
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $interval->message }}</p>
            @else
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Litres</dt>
                        <dd class="text-lg font-semibold">{{ number_format($interval->litres, 3, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Kilometres</dt>
                        <dd class="text-lg font-semibold">{{ number_format($interval->kilometres, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">L/100km</dt>
                        <dd class="text-lg font-semibold">{{ number_format($interval->l100km, 2, ',', '.') }}</dd>
                    </div>
                </dl>
            @endif
        @endif
    </div>

    {{ $this->table }}
</x-filament-panels::page>
