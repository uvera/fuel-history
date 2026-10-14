<?php

namespace App\Policies;

use App\Models\FuelVariant;
use App\Models\User;

class FuelVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FuelVariant $fuelVariant): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FuelVariant $fuelVariant): bool
    {
        return true;
    }

    public function delete(User $user, FuelVariant $fuelVariant): bool
    {
        return $fuelVariant->fuelEntries()->doesntExist();
    }
}
