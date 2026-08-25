<?php

namespace Tests\Feature;

use App\Filament\Widgets\GlobalEfficiencyChart;
use App\Filament\Widgets\GlobalSpendChart;
use App\Filament\Widgets\GlobalVolumeChart;
use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalAnalyticsWidgetsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ChartWidget::getData() is protected -- Filament exposes no public
     * test hook for it, so reflection is the standard way to verify chart
     * data directly rather than scraping the rendered Chart.js JSON out of
     * the DOM.
     */
    private function chartData(object $widgetInstance): array
    {
        $method = new \ReflectionMethod($widgetInstance, 'getData');
        $method->setAccessible(true);

        return $method->invoke($widgetInstance);
    }

    public function test_dashboard_renders_and_registers_the_analytics_widgets(): void
    {
        $user = User::factory()->create(['app_authentication_secret' => 'enrolled']);
        $this->actingAs($user);

        $this->get('/admin')->assertOk();

        // Widgets lazy-load by default (Filament\Support\Concerns\CanBeLazy
        // -- confirmed $isLazy = true), so the initial HTML only contains a
        // loading placeholder, not the widget's own heading text. The
        // correct thing to assert is that discoverWidgets() genuinely
        // registered these classes for the default Dashboard page
        // (Filament::getWidgets(), which Dashboard::getWidgets() returns
        // directly) -- not scrape lazily-rendered markup.
        $registered = Filament::getWidgets();
        $this->assertContains(GlobalSpendChart::class, $registered);
        $this->assertContains(GlobalVolumeChart::class, $registered);
        $this->assertContains(GlobalEfficiencyChart::class, $registered);
    }

    public function test_spend_chart_sums_across_two_cars_for_the_selected_month(): void
    {
        $user = User::factory()->create(['app_authentication_secret' => 'enrolled']);
        $carA = Car::factory()->create();
        $carB = Car::factory()->create();

        FuelEntry::factory()->create([
            'car_id' => $carA->id, 'user_id' => $user->id,
            'date_of_filling' => '2026-03-05', 'amount' => 4000, 'litres_filled' => 40,
        ]);
        FuelEntry::factory()->create([
            'car_id' => $carB->id, 'user_id' => $user->id,
            'date_of_filling' => '2026-03-10', 'amount' => 2500, 'litres_filled' => 25,
        ]);

        $this->actingAs($user);

        $component = Livewire::test(GlobalSpendChart::class);
        $data = $this->chartData($component->instance());

        $this->assertContains('2026-03', $data['labels']);
        $index = array_search('2026-03', $data['labels']);
        $this->assertEqualsWithDelta(6500.0, $data['datasets'][0]['data'][$index], 0.0001);
    }

    public function test_volume_and_efficiency_charts_render_without_error_when_there_is_no_data(): void
    {
        $user = User::factory()->create(['app_authentication_secret' => 'enrolled']);
        $this->actingAs($user);

        $volume = Livewire::test(GlobalVolumeChart::class);
        $this->assertSame([], $this->chartData($volume->instance())['labels']);

        $efficiency = Livewire::test(GlobalEfficiencyChart::class);
        $this->assertSame([], $this->chartData($efficiency->instance())['labels']);
    }
}
