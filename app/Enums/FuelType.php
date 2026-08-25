<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FuelType: string implements HasColor, HasLabel
{
    case Gasoline = 'gasoline';
    case Diesel = 'diesel';
    case Lpg = 'lpg';
    case Electric = 'electric';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gasoline => 'Gasoline',
            self::Diesel => 'Diesel',
            self::Lpg => 'LPG',
            self::Electric => 'Electric',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Gasoline => 'warning',
            self::Diesel => 'gray',
            self::Lpg => 'info',
            self::Electric => 'success',
        };
    }
}
