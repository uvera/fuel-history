<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_login_then_first_dashboard_request_routes_an_unenrolled_user_to_mfa_setup(): void
    {
        // Filament's Login::authenticate() itself redirects to the panel
        // home URL ("/admin") regardless of MFA enrollment state — the
        // mandatory-enrollment redirect to the setup page happens on a
        // separate, subsequent request via panel middleware, not as part
        // of the login redirect itself. Confirmed empirically: asserting
        // authenticate() redirects straight to the setup page fails
        // (it actually redirects to "/admin"), so this test exercises the
        // real two-step flow instead of assuming it's a single redirect.
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password-123',
            'app_authentication_secret' => null,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@example.com',
                'password' => 'correct-password-123',
            ])
            ->call('authenticate')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);

        $this->followingRedirects()
            ->get('/admin')
            ->assertOk()
            ->assertSee(__('filament-panels::auth/multi-factor/pages/set-up-required-multi-factor-authentication.heading'));
    }

    public function test_real_login_with_wrong_password_is_rejected_and_does_not_authenticate(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'correct-password-123',
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@example.com',
                'password' => 'totally-wrong-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors();

        $this->assertGuest();
    }

    public function test_user_without_mfa_enrolled_is_routed_to_setup_when_accessing_the_panel(): void
    {
        $user = User::factory()->create([
            'app_authentication_secret' => null,
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect('/admin/multi-factor-authentication/set-up');
    }

    public function test_user_with_mfa_enrolled_is_not_routed_to_setup(): void
    {
        $user = User::factory()->create([
            'app_authentication_secret' => 'a-fake-but-non-empty-secret',
        ]);

        $response = $this->actingAs($user)->get('/admin/multi-factor-authentication/set-up');

        // SetUpRequiredMultiFactorAuthentication::mount() redirects away
        // (to the intended/dashboard URL) once isEnabled() is true — see
        // vendor/filament/filament/src/Auth/MultiFactor/Pages/SetUpRequiredMultiFactorAuthentication.php.
        $response->assertRedirect('/admin');
    }

    public function test_every_authenticated_user_can_access_the_panel_regardless_of_role(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $regular = User::factory()->create(['role' => User::ROLE_USER]);

        $this->assertTrue($admin->canAccessPanel(filament()->getDefaultPanel()));
        $this->assertTrue($regular->canAccessPanel(filament()->getDefaultPanel()));
    }

    public function test_is_admin_reflects_the_role_column(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $regular = User::factory()->create(['role' => User::ROLE_USER]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($regular->isAdmin());
    }
}
