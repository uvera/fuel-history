<x-filament-widgets::widget class="fi-wi-table fi-wi-scrollable-table">
    <style>
        .fi-wi-scrollable-table .fi-ta-content-ctn {
            max-height: 24rem;
            overflow-y: auto;
        }
    </style>

    {{ $this->table }}
</x-filament-widgets::widget>
