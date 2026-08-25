<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class McpFuelHistoryServerTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user, array $abilities = ['mcp:use']): string
    {
        return $user->createToken('test', $abilities)->plainTextToken;
    }

    private function callTool(string $token, string $tool, array $arguments = []): TestResponse
    {
        return $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json, text/event-stream',
        ])->postJson('/mcp/fuel-history', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => $arguments],
        ]);
    }

    private function toolText(TestResponse $response): string
    {
        return $response->json('result.content.0.text');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/mcp/fuel-history', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => [],
        ])->assertStatus(401);
    }

    public function test_token_without_mcp_use_ability_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user, ['some-other-ability']);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/mcp/fuel-history', [
                'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => [],
            ])->assertStatus(403);
    }

    public function test_list_cars_returns_only_active_cars_by_default(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        Car::factory()->create(['name' => 'Active Car']);
        Car::factory()->create(['name' => 'Archived Car', 'archived_at' => now()]);

        $response = $this->callTool($token, 'list-cars');
        $text = $this->toolText($response);

        $this->assertStringContainsString('Active Car', $text);
        $this->assertStringNotContainsString('Archived Car', $text);
    }

    public function test_list_cars_includes_archived_when_requested(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        Car::factory()->create(['name' => 'Archived Car', 'archived_at' => now()]);

        $response = $this->callTool($token, 'list-cars', ['include_archived' => true]);

        $this->assertStringContainsString('Archived Car', $this->toolText($response));
    }

    public function test_add_fuel_entry_creates_an_entry_owned_by_the_token_holder(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $car = Car::factory()->create();

        $response = $this->callTool($token, 'add-fuel-entry', [
            'car_id' => $car->id,
            'gas_station' => 'Test Station',
            'litres_filled' => 40.5,
            'amount' => 5000,
            'odometer_at_fill' => 12345,
            'date_of_filling' => '2026-08-25 10:00:00',
        ]);

        $response->assertOk();
        $entry = FuelEntry::firstOrFail();
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame($car->id, $entry->car_id);
        $this->assertSame('RSD', $entry->currency);
    }

    public function test_add_fuel_entry_rejects_invalid_data(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $car = Car::factory()->create();

        $response = $this->callTool($token, 'add-fuel-entry', [
            'car_id' => $car->id,
            'gas_station' => 'Test Station',
            'litres_filled' => -5, // invalid: must be positive
            'amount' => 5000,
            'odometer_at_fill' => 12345,
            'date_of_filling' => '2026-08-25 10:00:00',
        ]);

        $this->assertSame(0, FuelEntry::count());
        $this->assertTrue((bool) $response->json('result.isError'));
    }

    public function test_list_fuel_entries_scoped_to_a_car(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $car = Car::factory()->create();
        $otherCar = Car::factory()->create();

        FuelEntry::factory()->create(['car_id' => $car->id, 'user_id' => $user->id, 'gas_station' => 'In Scope']);
        FuelEntry::factory()->create(['car_id' => $otherCar->id, 'user_id' => $user->id, 'gas_station' => 'Out Of Scope']);

        $response = $this->callTool($token, 'list-fuel-entries', ['car_id' => $car->id]);
        $text = $this->toolText($response);

        $this->assertStringContainsString('In Scope', $text);
        $this->assertStringNotContainsString('Out Of Scope', $text);
    }

    public function test_owner_can_edit_their_own_entry_via_mcp(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $entry = FuelEntry::factory()->create(['user_id' => $user->id]);

        $response = $this->callTool($token, 'edit-fuel-entry', [
            'entry_id' => $entry->id,
            'gas_station' => 'Updated Station',
        ]);

        $response->assertOk();
        $this->assertFalse((bool) $response->json('result.isError'));
        $this->assertSame('Updated Station', $entry->refresh()->gas_station);
    }

    public function test_non_owner_cannot_edit_another_users_entry_via_mcp(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = $this->tokenFor($otherUser);
        $entry = FuelEntry::factory()->create(['user_id' => $owner->id, 'gas_station' => 'Original']);

        $response = $this->callTool($token, 'edit-fuel-entry', [
            'entry_id' => $entry->id,
            'gas_station' => 'Hijacked',
        ]);

        $this->assertTrue((bool) $response->json('result.isError'));
        $this->assertSame('Original', $entry->refresh()->gas_station);
    }

    public function test_admin_can_edit_any_entry_via_mcp(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $token = $this->tokenFor($admin);
        $entry = FuelEntry::factory()->create(['user_id' => $owner->id]);

        $response = $this->callTool($token, 'edit-fuel-entry', [
            'entry_id' => $entry->id,
            'gas_station' => 'Admin Updated',
        ]);

        $this->assertFalse((bool) $response->json('result.isError'));
        $this->assertSame('Admin Updated', $entry->refresh()->gas_station);
    }

    public function test_owner_can_delete_their_own_entry_via_mcp(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $entry = FuelEntry::factory()->create(['user_id' => $user->id]);

        $response = $this->callTool($token, 'delete-fuel-entry', ['entry_id' => $entry->id]);

        $this->assertFalse((bool) $response->json('result.isError'));
        $this->assertDatabaseMissing('fuel_entries', ['id' => $entry->id]);
    }

    public function test_non_owner_cannot_delete_another_users_entry_via_mcp(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = $this->tokenFor($otherUser);
        $entry = FuelEntry::factory()->create(['user_id' => $owner->id]);

        $response = $this->callTool($token, 'delete-fuel-entry', ['entry_id' => $entry->id]);

        $this->assertTrue((bool) $response->json('result.isError'));
        $this->assertDatabaseHas('fuel_entries', ['id' => $entry->id]);
    }

    public function test_editing_a_nonexistent_entry_returns_an_error_not_a_crash(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->callTool($token, 'edit-fuel-entry', ['entry_id' => 999999, 'gas_station' => 'X']);

        $response->assertOk();
        $this->assertTrue((bool) $response->json('result.isError'));
    }
}
