<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => $state === User::ROLE_ADMIN ? 'warning' : 'gray')
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label('Created by')
                    ->placeholder('— root —'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()
                    ->visible(fn (User $record) => auth()->user()->can('update', $record)),
                DeleteAction::make()
                    ->visible(fn (User $record) => auth()->user()->can('delete', $record)),
            ]);
    }
}
