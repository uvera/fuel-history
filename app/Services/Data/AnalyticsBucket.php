<?php

namespace App\Services\Data;

/**
 * Totals for one period (a month/quarter/year label). l100kmSamples holds
 * the raw per-stretch L/100km values that closed in this period — kept
 * raw (not pre-averaged) so the global view can pool samples across cars
 * correctly rather than averaging already-averaged per-car figures, which
 * would silently misweight a car with few stretches equally to one with
 * many.
 */
final class AnalyticsBucket
{
    /**
     * @param  float[]  $l100kmSamples
     */
    public function __construct(
        public readonly string $label,
        public readonly float $totalAmount,
        public readonly float $totalLitres,
        public readonly array $l100kmSamples,
    ) {}

    public function averageL100km(): ?float
    {
        if ($this->l100kmSamples === []) {
            return null;
        }

        return array_sum($this->l100kmSamples) / count($this->l100kmSamples);
    }

    public function merge(AnalyticsBucket $other): self
    {
        return new self(
            $this->label,
            $this->totalAmount + $other->totalAmount,
            $this->totalLitres + $other->totalLitres,
            [...$this->l100kmSamples, ...$other->l100kmSamples],
        );
    }
}
