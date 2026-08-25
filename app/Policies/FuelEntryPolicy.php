<?php

namespace App\Policies;

use App\Models\FuelEntry;
use App\Models\User;

class FuelEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FuelEntry $fuelEntry): bool
    {
        return true;
    }

    /**
     * Any authenticated user (admin or regular) can log a fuel fill-up —
     * cars are a shared pool, not per-user assigned.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Owner or admin only — accountability for a shared car pool, per the
     * confirmed design decision (not "any user can edit any entry").
     */
    public function update(User $user, FuelEntry $fuelEntry): bool
    {
        return $user->isAdmin() || $user->id === $fuelEntry->user_id;
    }

    public function delete(User $user, FuelEntry $fuelEntry): bool
    {
        return $user->isAdmin() || $user->id === $fuelEntry->user_id;
    }
}
