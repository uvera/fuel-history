<x-filament-panels::page>
    <x-filament::section heading="All-time summary">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total spend (RSD)</p>
                <p class="text-lg font-semibold">{{ number_format($this->allTimeSummary->totalAmount, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total litres</p>
                <p class="text-lg font-semibold">{{ number_format($this->allTimeSummary->totalLitres, 3) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total distance (km)</p>
                <p class="text-lg font-semibold">{{ number_format($this->allTimeSummary->totalKm) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Avg L/100km</p>
                <p class="text-lg font-semibold">{{ $this->allTimeSummary->averageL100km === null ? '—' : number_format($this->allTimeSummary->averageL100km, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Cost per km (RSD)</p>
                <p class="text-lg font-semibold">{{ $this->allTimeSummary->costPerKm === null ? '—' : number_format($this->allTimeSummary->costPerKm, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Avg price/L (RSD)</p>
                <p class="text-lg font-semibold">{{ number_format($this->allTimeSummary->averagePricePerLitre, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Fill-ups</p>
                <p class="text-lg font-semibold">{{ $this->allTimeSummary->fillUpCount }}</p>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Cheapest fill-up</p>
                @if ($this->allTimeSummary->cheapestFillUp)
                    <p class="text-sm">{{ $this->allTimeSummary->cheapestFillUp->entry->date_of_filling->format('Y-m-d') }} — {{ $this->allTimeSummary->cheapestFillUp->entry->gas_station }} — {{ number_format($this->allTimeSummary->cheapestFillUp->pricePerLitre, 2) }} RSD/L</p>
                @else
                    <p class="text-sm">—</p>
                @endif
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Priciest fill-up</p>
                @if ($this->allTimeSummary->priciestFillUp)
                    <p class="text-sm">{{ $this->allTimeSummary->priciestFillUp->entry->date_of_filling->format('Y-m-d') }} — {{ $this->allTimeSummary->priciestFillUp->entry->gas_station }} — {{ number_format($this->allTimeSummary->priciestFillUp->pricePerLitre, 2) }} RSD/L</p>
                @else
                    <p class="text-sm">—</p>
                @endif
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
            <div class="overflow-x-auto">
                <p class="mb-2 text-sm font-medium">Spend by station</p>
                <table class="fi-ta-table w-full text-start">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-start text-xs font-semibold">Station</th>
                            <th class="px-3 py-2 text-start text-xs font-semibold">Amount (RSD)</th>
                            <th class="px-3 py-2 text-start text-xs font-semibold">Litres</th>
                            <th class="px-3 py-2 text-start text-xs font-semibold">Fill-ups</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->allTimeSummary->byStation as $station)
                            <tr>
                                <td class="px-3 py-2">{{ $station->gasStation }}</td>
                                <td class="px-3 py-2">{{ number_format($station->totalAmount, 2) }}</td>
                                <td class="px-3 py-2">{{ number_format($station->totalLitres, 3) }}</td>
                                <td class="px-3 py-2">{{ $station->fillUpCount }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-2" colspan="4">No fuel entries for this car yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto">
                <p class="mb-2 text-sm font-medium">Spend/efficiency by fuel type</p>
                <table class="fi-ta-table w-full text-start">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-start text-xs font-semibold">Fuel type</th>
                            <th class="px-3 py-2 text-start text-xs font-semibold">Amount (RSD)</th>
                            <th class="px-3 py-2 text-start text-xs font-semibold">Litres</th>
                            <th class="px-3 py-2 text-start text-xs font-semibold">Avg L/100km</th>
                            <th class="px-3 py-2 text-start text-xs font-semibold">Fill-ups</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->allTimeSummary->byFuelType as $fuelType)
                            <tr>
                                <td class="px-3 py-2">{{ $fuelType->label() }}</td>
                                <td class="px-3 py-2">{{ number_format($fuelType->totalAmount, 2) }}</td>
                                <td class="px-3 py-2">{{ number_format($fuelType->totalLitres, 3) }}</td>
                                <td class="px-3 py-2">{{ $fuelType->averageL100km === null ? '—' : number_format($fuelType->averageL100km, 2) }}</td>
                                <td class="px-3 py-2">{{ $fuelType->fillUpCount }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-2" colspan="5">No fuel entries for this car yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Analytics">
        <div class="mb-4 max-w-xs">
            <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">Period</label>
            <select wire:model.live="analyticsPeriod" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                <option value="month">Monthly</option>
                <option value="quarter">Quarterly</option>
                <option value="year">Yearly</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-start text-xs font-semibold">Period</th>
                        <th class="px-3 py-2 text-start text-xs font-semibold">Spend (RSD)</th>
                        <th class="px-3 py-2 text-start text-xs font-semibold">Litres</th>
                        <th class="px-3 py-2 text-start text-xs font-semibold">Avg L/100km</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->analyticsBuckets as $bucket)
                        <tr>
                            <td class="px-3 py-2">{{ $bucket['label'] }}</td>
                            <td class="px-3 py-2">{{ number_format($bucket['amount'], 2) }}</td>
                            <td class="px-3 py-2">{{ number_format($bucket['litres'], 3) }}</td>
                            <td class="px-3 py-2">{{ $bucket['avgL100km'] === null ? '—' : number_format($bucket['avgL100km'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-2" colspan="4">No fuel entries for this car yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
