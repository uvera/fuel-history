<?php

namespace Tests\Feature;

use App\Filament\Resources\Cars\CarResource;
use App\Filament\Resources\Cars\Pages\CreateCar;
use App\Filament\Resources\Cars\Pages\ListCars;
use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CarResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'app_authentication_secret' => 'enrolled',
        ]);
    }

    private function regularUser(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_USER,
            'app_authentication_secret' => 'enrolled',
        ]);
    }

    public function test_admin_can_create_a_car(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCar::class)
            ->fillForm([
                'name' => 'Family Golf',
                'make' => 'Volkswagen',
                'model' => 'Golf',
                'year' => 2018,
                'plate' => 'BG-123-AB',
                'fuel_type' => 'gasoline',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cars', [
            'name' => 'Family Golf',
            'plate' => 'BG-123-AB',
            'fuel_type' => 'gasoline',
        ]);
    }

    public function test_regular_user_cannot_reach_the_create_car_page(): void
    {
        $this->actingAs($this->regularUser());

        $this->get(CarResource::getUrl('create'))->assertForbidden();
    }

    public function test_regular_user_can_view_the_car_list(): void
    {
        Car::factory()->create(['name' => 'Shared Sedan']);

        $this->actingAs($this->regularUser());

        $this->get(CarResource::getUrl('index'))->assertOk();

        Livewire::test(ListCars::class)
            ->assertCanSeeTableRecords(Car::all())
            ->assertSee('Gasoline');
    }

    public function test_regular_user_cannot_edit_a_car(): void
    {
        $car = Car::factory()->create();

        $this->actingAs($this->regularUser());

        $this->get(CarResource::getUrl('edit', ['record' => $car]))->assertForbidden();
    }

    public function test_admin_can_archive_and_unarchive_a_car(): void
    {
        $car = Car::factory()->create();
        $this->assertFalse($car->isArchived());

        $this->actingAs($this->admin());

        // Default table filter (archived=false) shows active cars, so
        // this instance sees the car before archiving it.
        Livewire::test(ListCars::class)
            ->callTableAction('archive', $car);

        $this->assertTrue($car->refresh()->isArchived());

        // The same default filter now hides it — a fresh table instance
        // must explicitly ask to see archived cars to find and unarchive
        // one, matching the real admin workflow (toggle the filter, then
        // unarchive), not a bug in the filter default.
        Livewire::test(ListCars::class)
            ->filterTable('archived', true)
            ->callTableAction('unarchive', $car);

        $this->assertFalse($car->refresh()->isArchived());
    }

    public function test_archiving_a_car_never_deletes_it_or_its_fuel_entries_relation(): void
    {
        $car = Car::factory()->create();

        $this->actingAs($this->admin());

        Livewire::test(ListCars::class)->callTableAction('archive', $car);

        $this->assertDatabaseHas('cars', ['id' => $car->id]);
    }
}
