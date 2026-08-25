<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_their_profile_page(): void
    {
        $user = User::factory()->enrolled()->create();

        $this->actingAs($user)
            ->get('/admin/profile')
            ->assertOk();
    }

    public function test_user_can_change_their_own_password_from_the_profile_page(): void
    {
        $user = User::factory()->enrolled()->create(['password' => 'old-password-123']);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'password' => 'new-password-456',
                'passwordConfirmation' => 'new-password-456',
                'currentPassword' => 'old-password-123',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('new-password-456', $user->refresh()->password));
    }

    public function test_wrong_current_password_is_rejected_when_changing_password(): void
    {
        $user = User::factory()->enrolled()->create(['password' => 'old-password-123']);
        $originalHash = $user->password;

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'password' => 'new-password-456',
                'passwordConfirmation' => 'new-password-456',
                'currentPassword' => 'totally-wrong-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        $this->assertSame($originalHash, $user->refresh()->password);
    }
}
