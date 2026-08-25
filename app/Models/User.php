<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    public const string ROLE_ADMIN = 'admin';

    public const string ROLE_USER = 'user';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * When a manageable user is deleted, reparent their children to the
     * deleted user's own creator — so deleting someone mid-chain doesn't
     * accidentally promote a descendant admin to root (created_by null).
     * Root admins are never deletable (see UserPolicy), so this only ever
     * runs for users who themselves have a creator.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $user) {
            $user->createdUsers()->update(['created_by' => $user->created_by]);
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function createdUsers()
    {
        return $this->hasMany(self::class, 'created_by');
    }

    public function fuelEntries()
    {
        return $this->hasMany(FuelEntry::class);
    }

    /**
     * Every logged-in user (admin or regular) can access the panel — this
     * app has no anonymous/public area. Role-based restrictions (e.g.
     * admin-only car management) are enforced per-Resource via policies,
     * not at panel-access level.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * A "root" admin has no creator — bootstrapped via `users:make-admin`,
     * `db:seed`, or promoted from a pre-existing (pre-hierarchy) account.
     * Nobody can edit or delete a root admin from the panel.
     */
    public function isRootAdmin(): bool
    {
        return $this->created_by === null && $this->isAdmin();
    }

    /**
     * A regular (non-admin) user with no creator — pre-existing accounts
     * from before this feature, or anyone whose creator was later deleted
     * and had no creator of their own. Only root admins may manage these.
     */
    public function isUnownedRegular(): bool
    {
        return $this->created_by === null && ! $this->isAdmin();
    }

    /**
     * Whether $this created $target, directly or transitively. Walks the
     * created_by chain with a visited-id guard so a data anomaly (a cycle)
     * can never cause an infinite loop.
     */
    public function isAncestorOf(self $target): bool
    {
        $visited = [];
        $current = $target->createdBy;

        while ($current !== null) {
            if (in_array($current->id, $visited, true)) {
                return false;
            }

            if ($current->id === $this->id) {
                return true;
            }

            $visited[] = $current->id;
            $current = $current->createdBy;
        }

        return false;
    }

    /**
     * Subtree-only hierarchy rule: an admin may manage (edit/delete) only
     * people they created (directly or transitively), plus — if they are
     * themselves a root admin — any unowned regular user. Root admins can
     * never be managed by anyone, including other root admins. See the
     * "Admin user creation and hierarchy" design for the full rule matrix.
     */
    public function manages(self $target): bool
    {
        if ($this->id === $target->id || ! $this->isAdmin()) {
            return false;
        }

        if ($target->isRootAdmin()) {
            return false;
        }

        if ($target->isUnownedRegular()) {
            return $this->isRootAdmin();
        }

        return $this->isAncestorOf($target);
    }
}
