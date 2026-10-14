<?php

namespace App\Rules;

use App\Models\Car;
use App\Models\FuelEntry;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * fuel_variant is required unless the car's fuel type is electric.
 * Implicit so a missing value is still checked on create. When car_id
 * is absent (a partial edit that does not mention the car), the rule
 * does not fail on its own — the edit tool re-validates the effective
 * car_id plus variant with these same rules.
 */
class FuelVariantRequiredUnlessCarIsElectric implements DataAwareRule, ValidationRule
{
    public bool $implicit = true;

    /** @var array<string, mixed> */
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && trim($value) !== '') {
            return;
        }

        $carId = $this->data['car_id'] ?? null;

        if ($carId === null || $carId === '') {
            return;
        }

        $fuelType = Car::query()->find($carId)?->fuel_type;

        if (FuelEntry::fuelVariantIsRequired($fuelType)) {
            $fail('The fuel variant field is required unless the car is electric.');
        }
    }
}
