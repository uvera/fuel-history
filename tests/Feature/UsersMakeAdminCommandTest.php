<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersMakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_new_admin_user(): void
    {
        $this->artisan('users:make-admin')
            ->expectsQuestion('Email address', 'newadmin@example.com')
            ->expectsQuestion('Name', 'New Admin')
            ->expectsQuestion('Password', 'correct-password-123')
            ->expectsQuestion('Confirm password', 'correct-password-123')
            ->assertExitCode(0);

        $user = User::where('email', 'newadmin@example.com')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertSame('New Admin', $user->name);
    }

    public function test_refuses_to_create_a_user_when_passwords_do_not_match(): void
    {
        $this->artisan('users:make-admin')
            ->expectsQuestion('Email address', 'mismatch@example.com')
            ->expectsQuestion('Name', 'Someone')
            ->expectsQuestion('Password', 'correct-password-123')
            ->expectsQuestion('Confirm password', 'totally-different')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.com']);
    }

    public function test_promotes_an_existing_non_admin_user_when_confirmed(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com', 'role' => User::ROLE_USER]);

        $this->artisan('users:make-admin')
            ->expectsQuestion('Email address', 'existing@example.com')
            ->expectsConfirmation('A user with this email already exists ('.$user->name.'). Promote to admin?', 'yes')
            ->assertExitCode(0);

        $this->assertTrue($user->refresh()->isAdmin());
    }

    public function test_declining_promotion_makes_no_change(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com', 'role' => User::ROLE_USER]);

        $this->artisan('users:make-admin')
            ->expectsQuestion('Email address', 'existing@example.com')
            ->expectsConfirmation('A user with this email already exists ('.$user->name.'). Promote to admin?', 'no')
            ->assertExitCode(1);

        $this->assertFalse($user->refresh()->isAdmin());
    }

    public function test_already_admin_user_is_reported_without_prompting_for_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'role' => User::ROLE_ADMIN]);

        $this->artisan('users:make-admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->doesntExpectOutputToContain('Promote to admin?')
            ->assertExitCode(0);

        $this->assertTrue($user->refresh()->isAdmin());
    }
}
