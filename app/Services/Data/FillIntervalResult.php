<?php

namespace App\Services\Data;

/**
 * Average consumption between two chosen fills on one car: litres added
 * after the start fill through the end fill, over the odometer gap.
 * A refusal carries a message and no figures — see FillIntervalConsumption.
 */
final class FillIntervalResult
{
    private function __construct(
        public readonly ?float $litres,
        public readonly ?int $kilometres,
        public readonly ?float $l100km,
        public readonly ?string $message,
    ) {}

    public static function calculated(float $litres, int $kilometres, float $l100km): self
    {
        return new self($litres, $kilometres, $l100km, null);
    }

    public static function refused(string $message): self
    {
        return new self(null, null, null, $message);
    }
}
