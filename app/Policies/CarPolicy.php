<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\User;

class CarPolicy
{
    /**
     * Any authenticated user can see the car list (they need it to log
     * fuel entries against a car) — only mutation is admin-restricted.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Car $car): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Car $car): bool
    {
        return $user->isAdmin();
    }

    /**
     * Hard delete is never allowed — cars are archived, not deleted, so
     * fuel_entries.car_id is never orphaned. Filament's CarResource has no
     * delete action wired at all; this stays false as a safety default in
     * case anything ever calls it directly.
     */
    public function delete(User $user, Car $car): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Car $car): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Car $car): bool
    {
        return false;
    }
}
