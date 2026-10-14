<?php

namespace App\Services;

use App\Models\FuelEntry;
use App\Models\FuelStation;
use App\Models\FuelVariant;
use App\Services\Data\FuelCatalogResolution;
use App\Services\Data\FuelStationMergeResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Turns station and variant names into one shared catalog.
 * Matching ignores case and repeated spaces. The first saved spelling
 * is the display name: "OMV" stays "OMV" when a later fill says "omv".
 * A variant belongs to one station, so "95" at two stations is two rows.
 */
class FuelCatalog
{
    public static function displayName(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name));

        return $collapsed ?? '';
    }

    public static function nameKey(string $name): string
    {
        return mb_strtolower(self::displayName($name));
    }

    public function findOrCreateStation(string $name): FuelStation
    {
        $display = self::displayName($name);

        if ($display === '') {
            throw new InvalidArgumentException('Fuel station name is required.');
        }

        return FuelStation::query()->firstOrCreate(
            ['name_key' => self::nameKey($display)],
            ['name' => $display],
        );
    }

    public function findOrCreateVariant(FuelStation $station, ?string $name): ?FuelVariant
    {
        if ($name === null) {
            return null;
        }

        $display = self::displayName($name);

        if ($display === '') {
            return null;
        }

        return $station->variants()->firstOrCreate(
            ['name_key' => self::nameKey($display)],
            ['name' => $display],
        );
    }

    /**
     * Point an entry at the station and variant named on it, creating
     * catalog rows when they do not exist yet. A station change resolves
     * the variant against the new station.
     */
    public function classifyEntry(FuelEntry $entry): void
    {
        $station = $this->findOrCreateStation((string) $entry->gas_station);
        $variant = $this->findOrCreateVariant($station, $entry->fuel_variant);

        $entry->fuel_station_id = $station->id;
        $entry->fuel_variant_id = $variant?->id;
        $entry->gas_station = $station->name;
        $entry->fuel_variant = $variant?->name;
    }

    /**
     * Copy the selected catalog names onto the string columns the rest
     * of the app already reads.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function withCanonicalNames(array $data): array
    {
        $station = FuelStation::query()->find($data['fuel_station_id'] ?? null);

        if ($station === null) {
            throw ValidationException::withMessages([
                'fuel_station_id' => 'The selected fuel station is invalid.',
            ]);
        }

        $variantId = $data['fuel_variant_id'] ?? null;

        if ($variantId === '' || $variantId === null) {
            $data['fuel_station_id'] = $station->id;
            $data['fuel_variant_id'] = null;
            $data['gas_station'] = $station->name;
            $data['fuel_variant'] = null;

            return $data;
        }

        $variant = FuelVariant::query()->find($variantId);

        if ($variant === null || (int) $variant->fuel_station_id !== (int) $station->id) {
            throw ValidationException::withMessages([
                'fuel_variant_id' => 'The fuel variant must belong to the selected station.',
            ]);
        }

        $data['fuel_station_id'] = $station->id;
        $data['fuel_variant_id'] = $variant->id;
        $data['gas_station'] = $station->name;
        $data['fuel_variant'] = $variant->name;

        return $data;
    }

    public function stationNameIsAvailable(string $name, ?int $ignoreId = null): bool
    {
        $key = self::nameKey($name);

        if ($key === '') {
            return false;
        }

        return FuelStation::query()
            ->where('name_key', $key)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->doesntExist();
    }

    /**
     * Same station, and the names are the same grade written differently:
     * "V-Power" and "V Power", "95" and "Euro 95", or a short typo such
     * as "Diesel" and "Dizel". Different grades ("95" and "98") are not
     * similar, and neither are the same grade at two stations.
     */
    public static function variantsAreSimilar(FuelVariant $left, FuelVariant $right): bool
    {
        if ($left->is($right) || (int) $left->fuel_station_id !== (int) $right->fuel_station_id) {
            return false;
        }

        $leftTokens = self::nameTokens($left->name);
        $rightTokens = self::nameTokens($right->name);

        if ($leftTokens === [] || $rightTokens === []) {
            return false;
        }

        if (self::tokensCover($leftTokens, $rightTokens) || self::tokensCover($rightTokens, $leftTokens)) {
            return true;
        }

        if (count($leftTokens) === 1 && count($rightTokens) === 1) {
            $a = $leftTokens[0];
            $b = $rightTokens[0];

            if (strlen($a) >= 4 && strlen($b) >= 4 && levenshtein($a, $b) <= 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, FuelVariant>
     */
    public function similarVariants(FuelVariant $variant): Collection
    {
        $station = $variant->station;

        if ($station === null) {
            return collect();
        }

        return $station->variants()
            ->whereKeyNot($variant->getKey())
            ->orderBy('name')
            ->get()
            ->filter(fn (FuelVariant $other): bool => self::variantsAreSimilar($variant, $other))
            ->values();
    }

    /**
     * Move every fill on $source onto $target, then remove $source.
     * The kept variant's name is what those fills show afterwards.
     */
    public function mergeVariants(FuelVariant $source, FuelVariant $target): int
    {
        if (! self::variantsAreSimilar($source, $target)) {
            throw ValidationException::withMessages([
                'target_id' => 'Choose a similar variant from the same fuel station.',
            ]);
        }

        return DB::transaction(function () use ($source, $target): int {
            $moved = $source->fuelEntries()->update([
                'fuel_variant_id' => $target->id,
                'fuel_variant' => $target->name,
            ]);

            $source->delete();

            return $moved;
        });
    }

    /**
     * Move the source station's fills and variants onto the target, then
     * remove the source. A variant whose name already exists on the target
     * is combined into that variant. Every other variant is kept as its
     * own row on the target station.
     */
    public function mergeStations(FuelStation $source, FuelStation $target): FuelStationMergeResult
    {
        if ($source->is($target)) {
            throw ValidationException::withMessages([
                'target_id' => 'Choose a different fuel station to keep.',
            ]);
        }

        return DB::transaction(function () use ($source, $target): FuelStationMergeResult {
            $entriesMoved = 0;
            $variantsMoved = 0;
            $variantsCombined = 0;

            $source->variants()->orderBy('id')->get()->each(function (FuelVariant $variant) use ($target, &$entriesMoved, &$variantsMoved, &$variantsCombined): void {
                $existing = $target->variants()->where('name_key', $variant->name_key)->first();

                if ($existing !== null) {
                    $entriesMoved += $variant->fuelEntries()->update([
                        'fuel_station_id' => $target->id,
                        'gas_station' => $target->name,
                        'fuel_variant_id' => $existing->id,
                        'fuel_variant' => $existing->name,
                    ]);
                    $variant->delete();
                    $variantsCombined++;

                    return;
                }

                $variant->fuel_station_id = $target->id;
                $variant->save();

                $entriesMoved += $variant->fuelEntries()->update([
                    'fuel_station_id' => $target->id,
                    'gas_station' => $target->name,
                ]);
                $variantsMoved++;
            });

            $entriesMoved += $source->fuelEntries()->update([
                'fuel_station_id' => $target->id,
                'gas_station' => $target->name,
            ]);

            if ($source->delete() === false) {
                throw new RuntimeException('The fuel station could not be removed.');
            }

            return new FuelStationMergeResult($entriesMoved, $variantsMoved, $variantsCombined);
        });
    }

    /**
     * @return array<int, string>
     */
    private static function nameTokens(string $name): array
    {
        $spaced = preg_replace('/[^a-z0-9]+/u', ' ', self::nameKey($name)) ?? '';
        $spaced = preg_replace('/(?<=\d)(?=[a-z])|(?<=[a-z])(?=\d)/', ' ', $spaced) ?? '';

        return array_values(array_filter(
            explode(' ', $spaced),
            fn (string $token): bool => $token !== '',
        ));
    }

    /**
     * @param  array<int, string>  $shorter
     * @param  array<int, string>  $longer
     */
    private static function tokensCover(array $shorter, array $longer): bool
    {
        if (array_diff($shorter, $longer) !== []) {
            return false;
        }

        foreach ($shorter as $token) {
            if (strlen($token) >= 2) {
                return true;
            }
        }

        return false;
    }

    public function variantNameIsAvailable(FuelStation $station, string $name, ?int $ignoreId = null): bool
    {
        $key = self::nameKey($name);

        if ($key === '') {
            return false;
        }

        return $station->variants()
            ->where('name_key', $key)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->doesntExist();
    }

    /**
     * Classify every fuel entry from the names already stored on it.
     * A second pass creates nothing and only rewrites rows whose text
     * or links do not already match the catalog.
     */
    public function resolveFromFuelEntries(): FuelCatalogResolution
    {
        return DB::transaction(function (): FuelCatalogResolution {
            $stationsCreated = 0;
            $variantsCreated = 0;
            $entriesLinked = 0;

            FuelEntry::query()->orderBy('id')->lazyById()->each(function (FuelEntry $entry) use (&$stationsCreated, &$variantsCreated, &$entriesLinked): void {
                $stationKey = self::nameKey((string) $entry->gas_station);
                $stationExisted = $stationKey !== '' && FuelStation::query()->where('name_key', $stationKey)->exists();
                $station = $this->findOrCreateStation((string) $entry->gas_station);

                if (! $stationExisted) {
                    $stationsCreated++;
                }

                $variant = null;
                $variantName = $entry->fuel_variant;

                if (is_string($variantName) && self::displayName($variantName) !== '') {
                    $variantKey = self::nameKey($variantName);
                    $variantExisted = $station->variants()->where('name_key', $variantKey)->exists();
                    $variant = $this->findOrCreateVariant($station, $variantName);

                    if (! $variantExisted) {
                        $variantsCreated++;
                    }
                }

                $entry->fuel_station_id = $station->id;
                $entry->fuel_variant_id = $variant?->id;
                $entry->gas_station = $station->name;
                $entry->fuel_variant = $variant?->name;

                if ($entry->isDirty(['fuel_station_id', 'fuel_variant_id', 'gas_station', 'fuel_variant'])) {
                    $entry->save();
                    $entriesLinked++;
                }
            });

            return new FuelCatalogResolution($stationsCreated, $variantsCreated, $entriesLinked);
        });
    }
}
