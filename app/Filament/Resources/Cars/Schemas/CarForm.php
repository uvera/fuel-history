<?php

namespace App\Filament\Resources\Cars\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('make')
                    ->maxLength(255),
                TextInput::make('model')
                    ->maxLength(255),
                TextInput::make('year')
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue((int) date('Y') + 1),
                TextInput::make('plate')
                    ->maxLength(255),
            ]);
    }
}
