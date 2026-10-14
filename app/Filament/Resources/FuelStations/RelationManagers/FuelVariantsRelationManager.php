<?php

namespace App\Filament\Resources\FuelStations\RelationManagers;

use App\Models\FuelStation;
use App\Models\FuelVariant;
use App\Services\FuelCatalog;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FuelVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Fuel variants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Variant')
                    ->required()
                    ->maxLength(255)
                    ->rule(function (RelationManager $livewire, ?FuelVariant $record): Closure {
                        return function (string $attribute, mixed $value, Closure $fail) use ($livewire, $record): void {
                            $name = (string) $value;

                            if (FuelCatalog::displayName($name) === '') {
                                $fail('The name field is required.');

                                return;
                            }

                            /** @var FuelStation $station */
                            $station = $livewire->getOwnerRecord();

                            if (! app(FuelCatalog::class)->variantNameIsAvailable($station, $name, $record?->getKey())) {
                                $fail('A fuel variant with this name already exists for this station.');
                            }
                        };
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Variant')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('merge')
                    ->label('Merge')
                    ->modalHeading('Merge fuel variants')
                    ->modalDescription('Fuel entries using this variant will point at the one you keep, and this variant will be removed.')
                    ->visible(fn (FuelVariant $record): bool => app(FuelCatalog::class)->similarVariants($record)->isNotEmpty())
                    ->schema([
                        Select::make('target_id')
                            ->label('Keep')
                            ->options(fn (FuelVariant $record): array => app(FuelCatalog::class)
                                ->similarVariants($record)
                                ->mapWithKeys(fn (FuelVariant $variant): array => [$variant->id => $variant->name])
                                ->all())
                            ->required(),
                    ])
                    ->action(function (FuelVariant $record, array $data): void {
                        $target = FuelVariant::query()->findOrFail($data['target_id']);
                        $moved = app(FuelCatalog::class)->mergeVariants($record, $target);

                        Notification::make()
                            ->title('Variants merged')
                            ->body("{$moved} fuel entries now use {$target->name}.")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (FuelVariant $record): bool => auth()->user()->can('delete', $record)),
            ]);
    }
}
