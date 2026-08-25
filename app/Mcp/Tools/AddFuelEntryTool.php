<?php

namespace App\Mcp\Tools;

use App\Models\FuelEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add-fuel-entry')]
#[Description('Logs a fuel fill-up for a car. Use list-cars first to find the car_id. Currency is always RSD. Set is_partial_fill=true and optionally trip_km (kilometres since the last fill, from the car\'s trip computer) when the tank was not filled to full.')]
class AddFuelEntryTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(FuelEntry::validationRules());

        $entry = FuelEntry::create([
            ...$validated,
            // The token owner is always the entry's owner -- never a tool
            // input, same discipline as the Filament form.
            'user_id' => $request->user()->id,
        ]);

        return Response::text(json_encode([
            'id' => $entry->id,
            'message' => "Fuel entry #{$entry->id} logged for car #{$entry->car_id}.",
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'car_id' => $schema->integer()
                ->description('The car to log this fill-up against. Use list-cars to look this up.')
                ->required(),
            'gas_station' => $schema->string()
                ->description('Name of the gas station.')
                ->required(),
            'litres_filled' => $schema->number()
                ->description('Litres of fuel put in.')
                ->required(),
            'amount' => $schema->number()
                ->description('Amount paid, in RSD.')
                ->required(),
            'odometer_at_fill' => $schema->integer()
                ->description('Odometer reading (km) at the time of this fill-up. Omit if unknown, e.g. a receipt-only entry with no odometer photo.'),
            'date_of_filling' => $schema->string()
                ->description('Date/time of the fill-up, e.g. 2026-08-25 14:30.')
                ->required(),
            'is_partial_fill' => $schema->boolean()
                ->description('True if the tank was not filled to full.')
                ->default(false),
            'trip_km' => $schema->number()
                ->description('Kilometres since the last fill, from the trip computer -- only meaningful when is_partial_fill is true.'),
        ];
    }
}
