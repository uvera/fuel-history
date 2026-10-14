<?php

namespace Database\Factories;

use App\Models\FuelStation;
use App\Models\FuelVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelVariant>
 */
class FuelVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fuel_station_id' => FuelStation::factory(),
            'name' => fake()->unique()->bothify('Grade ##??'),
        ];
    }
}
