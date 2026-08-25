<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\FuelEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->enrolled()->create();
    }

    private function regularUser(): User
    {
        return User::factory()->enrolled()->create();
    }

    public function test_regular_user_cannot_reach_the_users_list(): void
    {
        $this->actingAs($this->regularUser());

        $this->get(UserResource::getUrl('index'))->assertForbidden();
    }

    public function test_regular_user_cannot_reach_the_create_user_page(): void
    {
        $this->actingAs($this->regularUser());

        $this->get(UserResource::getUrl('create'))->assertForbidden();
    }

    public function test_admin_can_create_a_regular_user_owned_by_the_creator(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'correct-password-123',
                'role' => User::ROLE_USER,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertSame($admin->id, $created->created_by);
        $this->assertFalse($created->isAdmin());
    }

    public function test_admin_can_create_a_nested_admin_owned_by_the_creator(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Admin',
                'email' => 'newadmin@example.com',
                'password' => 'correct-password-123',
                'role' => User::ROLE_ADMIN,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'newadmin@example.com')->firstOrFail();
        $this->assertSame($admin->id, $created->created_by);
        $this->assertTrue($created->isAdmin());
        $this->assertFalse($created->isRootAdmin());
    }

    public function test_nested_admin_cannot_edit_or_delete_its_parent(): void
    {
        $root = $this->admin();
        $nestedAdmin = User::factory()->admin()->enrolled()->createdBy($root)->create();

        $this->actingAs($nestedAdmin);

        $this->get(UserResource::getUrl('edit', ['record' => $root]))->assertForbidden();
        $this->assertFalse($nestedAdmin->can('update', $root));
        $this->assertFalse($nestedAdmin->can('delete', $root));
    }

    public function test_root_admin_can_edit_and_delete_a_nested_descendant(): void
    {
        $root = $this->admin();
        $child = User::factory()->admin()->enrolled()->createdBy($root)->create();
        $grandchild = User::factory()->enrolled()->createdBy($child)->create();

        $this->actingAs($root);

        $this->get(UserResource::getUrl('edit', ['record' => $grandchild]))->assertOk();

        Livewire::test(EditUser::class, ['record' => $grandchild->id])
            ->fillForm(['name' => 'Updated Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated Name', $grandchild->refresh()->name);
        $this->assertTrue($root->can('delete', $grandchild));
    }

    public function test_root_admin_can_manage_unowned_regular_user(): void
    {
        $root = $this->admin();
        $unownedRegular = $this->regularUser();

        $this->assertTrue($root->can('update', $unownedRegular));
        $this->assertTrue($root->can('delete', $unownedRegular));
    }

    public function test_nested_admin_cannot_manage_unowned_regular_user(): void
    {
        $root = $this->admin();
        $nestedAdmin = User::factory()->admin()->enrolled()->createdBy($root)->create();
        $unownedRegular = $this->regularUser();

        $this->assertFalse($nestedAdmin->can('update', $unownedRegular));
        $this->assertFalse($nestedAdmin->can('delete', $unownedRegular));
    }

    public function test_root_admin_edit_url_is_forbidden_to_everyone(): void
    {
        $rootA = $this->admin();
        $rootB = $this->admin();
        $nestedAdmin = User::factory()->admin()->enrolled()->createdBy($rootB)->create();

        $this->actingAs($rootB);
        $this->get(UserResource::getUrl('edit', ['record' => $rootA]))->assertForbidden();

        $this->actingAs($nestedAdmin);
        $this->get(UserResource::getUrl('edit', ['record' => $rootB]))->assertForbidden();
    }

    public function test_deleting_a_user_reparents_their_children(): void
    {
        $root = $this->admin();
        $child = User::factory()->admin()->enrolled()->createdBy($root)->create();
        $grandchild = User::factory()->enrolled()->createdBy($child)->create();

        $this->actingAs($root);

        Livewire::test(EditUser::class, ['record' => $child->id])
            ->callAction('delete');

        $this->assertDatabaseMissing('users', ['id' => $child->id]);
        $this->assertSame($root->id, $grandchild->refresh()->created_by);
    }

    public function test_cannot_delete_a_user_who_has_fuel_entries(): void
    {
        $root = $this->admin();
        $child = User::factory()->admin()->enrolled()->createdBy($root)->create();
        FuelEntry::factory()->create(['user_id' => $child->id]);

        $this->assertTrue($root->can('update', $child));
        $this->assertFalse($root->can('delete', $child));
    }
}
