<?php

namespace App\Services;

use App\Models\FuelEntry;
use App\Services\Data\FillIntervalResult;
use Illuminate\Support\Collection;

/**
 * Average consumption between any two fills on a single car — a pure,
 * DB-free pass over that car's entries, ordered the same way as
 * FuelStretchService (date_of_filling, then id).
 *
 * Litres are every fill after the start fill through the end fill,
 * including partials and fills with no odometer. The start fill's own
 * litres are excluded. Kilometres are the odometer gap between the two
 * endpoints. This is separate from the full-tank L/100km column: a full
 * fill in the middle still counts toward the litres, it does not reset
 * the interval.
 */
class FillIntervalConsumption
{
    public const MESSAGE_SAME_FILL = 'Choose two different fills.';

    public const MESSAGE_NOT_CHRONOLOGICAL = 'The start fill must be earlier than the end fill.';

    public const MESSAGE_OTHER_CAR = 'Both fills must belong to this car.';

    public const MESSAGE_MISSING_ODOMETER = 'Both fills need an odometer reading.';

    public const MESSAGE_ODOMETER_GAP = 'The odometer must increase between the two fills.';

    /**
     * @param  Collection<int, FuelEntry>  $entries  All of one car's entries, any order — sorted internally by date_of_filling then id.
     */
    public function compute(Collection $entries, FuelEntry $start, FuelEntry $end): FillIntervalResult
    {
        if ((int) $start->id === (int) $end->id) {
            return FillIntervalResult::refused(self::MESSAGE_SAME_FILL);
        }

        $ordered = $entries
            ->sortBy([
                ['date_of_filling', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $startIndex = $this->indexOf($ordered, (int) $start->id);
        $endIndex = $this->indexOf($ordered, (int) $end->id);

        if ($startIndex === null || $endIndex === null) {
            if (! $this->isStrictlyBefore($start, $end)) {
                return FillIntervalResult::refused(self::MESSAGE_NOT_CHRONOLOGICAL);
            }

            return FillIntervalResult::refused(self::MESSAGE_OTHER_CAR);
        }

        if ($startIndex > $endIndex) {
            return FillIntervalResult::refused(self::MESSAGE_NOT_CHRONOLOGICAL);
        }

        if (! $this->onThisCar($ordered, $start, $end, $startIndex, $endIndex)) {
            return FillIntervalResult::refused(self::MESSAGE_OTHER_CAR);
        }

        /** @var FuelEntry $startEntry */
        $startEntry = $ordered[$startIndex];
        /** @var FuelEntry $endEntry */
        $endEntry = $ordered[$endIndex];

        if ($startEntry->odometer_at_fill === null || $endEntry->odometer_at_fill === null) {
            return FillIntervalResult::refused(self::MESSAGE_MISSING_ODOMETER);
        }

        $kilometres = (int) $endEntry->odometer_at_fill - (int) $startEntry->odometer_at_fill;

        if ($kilometres <= 0) {
            return FillIntervalResult::refused(self::MESSAGE_ODOMETER_GAP);
        }

        $litres = 0.0;

        foreach ($ordered as $index => $entry) {
            if ($index > $startIndex && $index <= $endIndex) {
                $litres += (float) $entry->litres_filled;
            }
        }

        return FillIntervalResult::calculated($litres, $kilometres, ($litres / $kilometres) * 100);
    }

    /**
     * @param  Collection<int, FuelEntry>  $ordered
     */
    private function indexOf(Collection $ordered, int $id): ?int
    {
        $index = $ordered->search(fn (FuelEntry $entry): bool => (int) $entry->id === $id);

        return $index === false ? null : $index;
    }

    private function isStrictlyBefore(FuelEntry $start, FuelEntry $end): bool
    {
        $ordered = collect([$end, $start])
            ->sortBy([
                ['date_of_filling', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        return (int) $ordered[0]->id === (int) $start->id
            && (int) $ordered[1]->id === (int) $end->id;
    }

    /**
     * @param  Collection<int, FuelEntry>  $ordered
     */
    private function onThisCar(Collection $ordered, FuelEntry $start, FuelEntry $end, int $startIndex, int $endIndex): bool
    {
        if ($start->car_id === null || $end->car_id === null) {
            return false;
        }

        if ((int) $start->car_id !== (int) $end->car_id) {
            return false;
        }

        /** @var FuelEntry $startEntry */
        $startEntry = $ordered[$startIndex];
        /** @var FuelEntry $endEntry */
        $endEntry = $ordered[$endIndex];

        if ((int) $startEntry->car_id !== (int) $start->car_id || (int) $endEntry->car_id !== (int) $end->car_id) {
            return false;
        }

        return $ordered->every(
            fn (FuelEntry $entry): bool => (int) $entry->car_id === (int) $start->car_id,
        );
    }
}
