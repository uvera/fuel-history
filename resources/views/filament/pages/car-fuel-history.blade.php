<x-filament-panels::page>
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
