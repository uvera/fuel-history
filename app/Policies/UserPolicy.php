<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Subtree-only hierarchy rule — see User::manages() for the full
     * matrix (root admins are never editable, nested admins are editable
     * by their ancestors, unowned regulars only by root admins).
     */
    public function update(User $user, User $model): bool
    {
        return $user->manages($model);
    }

    /**
     * Same hierarchy rule as update, plus: a user with fuel entries can
     * never be deleted — fuel_entries.user_id is RESTRICT ON DELETE, so
     * this mirrors that DB-level guarantee at the application layer
     * instead of surfacing a raw FK-violation error.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->manages($model) && ! $model->fuelEntries()->exists();
    }
}
