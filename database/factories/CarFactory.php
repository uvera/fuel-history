<?php

namespace Database\Factories;

use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'make' => fake()->company(),
            'model' => fake()->word(),
            'year' => fake()->numberBetween(2000, (int) date('Y')),
            'plate' => strtoupper(fake()->bothify('??-###-??')),
        ];
    }
}
