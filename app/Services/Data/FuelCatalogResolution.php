<?php

namespace App\Services\Data;

/**
 * How many catalog rows were created, and how many fuel entries were
 * relinked, by one pass over the fuel history.
 */
final class FuelCatalogResolution
{
    public function __construct(
        public readonly int $stationsCreated,
        public readonly int $variantsCreated,
        public readonly int $entriesLinked,
    ) {}
}
