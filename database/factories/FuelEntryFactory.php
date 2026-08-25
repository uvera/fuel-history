<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\FuelEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelEntry>
 */
class FuelEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'car_id' => Car::factory(),
            'user_id' => User::factory(),
            'gas_station' => fake()->company(),
            'litres_filled' => fake()->randomFloat(3, 5, 60),
            'amount' => fake()->randomFloat(2, 500, 8000),
            'odometer_at_fill' => fake()->numberBetween(0, 300000),
            'date_of_filling' => fake()->dateTimeBetween('-1 year', 'now'),
            'is_partial_fill' => false,
            'trip_km' => null,
        ];
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_partial_fill' => true,
        ]);
    }
}
