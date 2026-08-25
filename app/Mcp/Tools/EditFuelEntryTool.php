<?php

namespace App\Mcp\Tools;

use App\Enums\FuelType;
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
#[Description('Edits a fuel entry. Only the entry\'s owner or an admin may edit it -- same rule as the app itself. Only include the fields you want to change; entry_id is the only required field. If you set fuel_type to a non-electric value, also set fuel_variant.')]
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

        // Partial edits validate each sent field in isolation, so patching
        // just fuel_type (or sending an explicit null fuel_variant) could
        // otherwise leave a persisted non-Electric entry with no
        // fuel_variant. Recompute the *effective* post-edit state -- the
        // sent value if this call touches the field, otherwise the
        // entry's current value -- and re-check it against the same
        // fuel_variant rule validationRules() already defines, so this
        // never drifts from the single source of truth. Only enforced once
        // a fuel_type is actually established; legacy NULL rows are never
        // retroactively forced to complete themselves via an unrelated edit.
        $effectiveState = [
            'fuel_type' => array_key_exists('fuel_type', $validated) ? $validated['fuel_type'] : $entry->fuel_type?->value,
            'fuel_variant' => array_key_exists('fuel_variant', $validated) ? $validated['fuel_variant'] : $entry->fuel_variant,
        ];

        if ($effectiveState['fuel_type'] !== null) {
            $crossFieldValidator = Validator::make(
                $effectiveState,
                Arr::only(FuelEntry::validationRules(), ['fuel_variant']),
            );

            if ($crossFieldValidator->fails()) {
                return Response::error($crossFieldValidator->errors()->first('fuel_variant'));
            }
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
                ->enum(FuelType::class)
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
