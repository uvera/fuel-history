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

#[Name('edit-fuel-entry')]
#[Description('Edits a fuel entry. Only the entry\'s owner or an admin may edit it -- same rule as the app itself. Only include the fields you want to change; entry_id is the only required field. If you set fuel_type to a non-electric value, also set fuel_variant.')]
class EditFuelEntryTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'entry_id' => ['required', 'integer'],
            ...FuelEntry::validationRules(allFieldsRequired: false),
        ]);

        $entry = FuelEntry::find($validated['entry_id']);

        if ($entry === null) {
            return Response::error("Fuel entry #{$validated['entry_id']} does not exist.");
        }

        // Reuses FuelEntryPolicy::update exactly -- owner or admin, the
        // same rule enforced in the Filament panel. No separate
        // authorization logic for this entry point.
        if (! $request->user()->can('update', $entry)) {
            return Response::error('You can only edit fuel entries you logged yourself, unless you are an admin.');
        }

        unset($validated['entry_id']);
        $entry->update($validated);

        return Response::text("Fuel entry #{$entry->id} updated.");
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'entry_id' => $schema->integer()
                ->description('The fuel entry to edit. Use list-fuel-entries to find it.')
                ->required(),
            'car_id' => $schema->integer()
                ->description('Move this entry to a different car.'),
            'fuel_type' => $schema->string()
                ->description('One of: gasoline, diesel, lpg, electric.'),
            'fuel_variant' => $schema->string()
                ->description('The specific grade or product, e.g. "95", "98", "B7", "Shell V-Power".'),
            'gas_station' => $schema->string()
                ->description('Name of the gas station.'),
            'litres_filled' => $schema->number()
                ->description('Litres of fuel put in.'),
            'amount' => $schema->number()
                ->description('Amount paid, in RSD.'),
            'odometer_at_fill' => $schema->integer()
                ->description('Odometer reading (km) at the time of this fill-up.'),
            'date_of_filling' => $schema->string()
                ->description('Date/time of the fill-up, e.g. 2026-08-25 14:30.'),
            'is_partial_fill' => $schema->boolean()
                ->description('True if the tank was not filled to full.'),
            'trip_km' => $schema->number()
                ->description('Kilometres since the last fill, from the trip computer.'),
        ];
    }
}
