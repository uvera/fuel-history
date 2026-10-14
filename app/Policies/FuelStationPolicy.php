<?php

namespace App\Policies;

use App\Models\FuelStation;
use App\Models\User;

class FuelStationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FuelStation $fuelStation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FuelStation $fuelStation): bool
    {
        return true;
    }

    /**
     * Entries reference the station with a restrict-on-delete foreign
     * key. Variants of an unused station are removed with it.
     */
    public function delete(User $user, FuelStation $fuelStation): bool
    {
        return $fuelStation->fuelEntries()->doesntExist();
    }
}
