<?php

namespace App\Filament\Widgets\Concerns;

use App\Services\AnalyticsPeriod;

trait HasAnalyticsPeriodFilter
{
    protected function getFilters(): ?array
    {
        return [
            'month' => 'Monthly',
            'quarter' => 'Quarterly',
            'year' => 'Yearly',
        ];
    }

    protected function period(): AnalyticsPeriod
    {
        return AnalyticsPeriod::from($this->filter ?? 'month');
    }
}
