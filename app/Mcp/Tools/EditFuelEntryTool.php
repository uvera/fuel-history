<?php

namespace App\Mcp\Tools;

use App\Models\FuelEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('edit-fuel-entry')]
#[Description('Edits a fuel entry. Only the entry\'s owner or an admin may edit it -- same rule as the app itself. Only include the fields you want to change; entry_id is the only required field. fuel_variant is required unless the car\'s fuel type is electric. If you move the entry to a non-electric car, include fuel_variant unless the entry already has one.')]
class EditFuelEntryTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'entry_id' => ['required', 'integer'],
            ...FuelEntry::validationRules(allFieldsRequired: false),
        ]);

        if (($validated['fuel_variant'] ?? null) === '') {
            $validated['fuel_variant'] = null;
        }

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

        // Partial edits validate each sent field in isolation, so omitting
        // fuel_variant (or sending null) while the effective car is not
        // electric — including a car change — could leave a fill with no
        // variant. Re-check the effective car and variant against the same
        // fuel_variant rules validationRules() already defines.
        $effectiveState = [
            'car_id' => array_key_exists('car_id', $validated) ? $validated['car_id'] : $entry->car_id,
            'fuel_variant' => array_key_exists('fuel_variant', $validated) ? $validated['fuel_variant'] : $entry->fuel_variant,
        ];

        $crossFieldValidator = Validator::make(
            $effectiveState,
            Arr::only(FuelEntry::validationRules(), ['fuel_variant']),
        );

        if ($crossFieldValidator->fails()) {
            return Response::error($crossFieldValidator->errors()->first('fuel_variant'));
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
            'fuel_variant' => $schema->string()
                ->description('The specific grade or product, e.g. "95", "98", "B7", "Shell V-Power". Required unless the car\'s fuel type is electric.'),
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
