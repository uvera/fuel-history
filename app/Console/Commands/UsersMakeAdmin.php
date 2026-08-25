<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('users:make-admin')]
#[Description('Create a new admin user, or promote an existing user to admin')]
class UsersMakeAdmin extends Command
{
    public function handle(): int
    {
        $email = text(
            label: 'Email address',
            required: true,
            validate: fn (string $value) => Validator::make(
                ['email' => $value],
                ['email' => ['required', 'email']],
            )->errors()->first('email'),
        );

        $existing = User::where('email', $email)->first();

        if ($existing) {
            return $this->promoteExisting($existing);
        }

        return $this->createNewAdmin($email);
    }

    private function promoteExisting(User $user): int
    {
        if ($user->isAdmin()) {
            $this->components->info("{$user->email} is already an admin.");

            return self::SUCCESS;
        }

        if (! confirm("A user with this email already exists ({$user->name}). Promote to admin?")) {
            $this->components->warn('Aborted -- no changes made.');

            return self::FAILURE;
        }

        $user->update(['role' => User::ROLE_ADMIN]);
        $this->components->info("{$user->email} has been promoted to admin.");

        return self::SUCCESS;
    }

    private function createNewAdmin(string $email): int
    {
        $name = text(label: 'Name', required: true);

        $password = password(
            label: 'Password',
            required: true,
            validate: fn (string $value) => Validator::make(
                ['password' => $value],
                ['password' => ['required', 'string', 'min:8']],
            )->errors()->first('password'),
        );

        $confirmedPassword = password(label: 'Confirm password', required: true);

        if ($password !== $confirmedPassword) {
            $this->components->error('Passwords do not match -- no user was created.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->components->info(
            "Admin user {$email} created. They'll be prompted to set up two-factor authentication on first login."
        );

        return self::SUCCESS;
    }
}
