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

#[Name('list-fuel-entries')]
#[Description('Lists recent fuel entries, most recent first. Pass car_id to scope to one car (useful for finding the last odometer reading before adding a new entry), gas_station to match by name (partial match), and/or user_id to scope to a specific user\'s entries. Omit car_id and user_id both to see your own most recent entries across all cars. Returns entry IDs needed for edit-fuel-entry / delete-fuel-entry.')]
class ListFuelEntriesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'car_id' => ['sometimes', 'integer', 'exists:cars,id'],
            'gas_station' => ['sometimes', 'string'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = $validated['limit'] ?? 10;

        $query = FuelEntry::query()
            ->with('car')
            ->when(array_key_exists('car_id', $validated), fn ($q) => $q->where('car_id', $validated['car_id']))
            ->when(array_key_exists('user_id', $validated), fn ($q) => $q->where('user_id', $validated['user_id']))
            ->when(
                ! array_key_exists('car_id', $validated) && ! array_key_exists('user_id', $validated),
                fn ($q) => $q->where('user_id', $request->user()->id),
            )
            ->when(array_key_exists('gas_station', $validated), fn ($q) => $q->where('gas_station', 'like', '%'.$validated['gas_station'].'%'))
            ->orderByDesc('date_of_filling')
            ->orderByDesc('id')
            ->limit($limit);

        $entries = $query->get()->map(fn (FuelEntry $entry): array => [
            'id' => $entry->id,
            'car_id' => $entry->car_id,
            'fuel_type' => $entry->car->fuel_type->value,
            'fuel_variant' => $entry->fuel_variant,
            'date_of_filling' => $entry->date_of_filling->toDateTimeString(),
            'gas_station' => $entry->gas_station,
            'litres_filled' => (float) $entry->litres_filled,
            'amount' => (float) $entry->amount,
            'odometer_at_fill' => $entry->odometer_at_fill,
            'is_partial_fill' => $entry->is_partial_fill,
            'trip_km' => $entry->trip_km === null ? null : (float) $entry->trip_km,
        ])->all();

        return Response::text(json_encode($entries, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'car_id' => $schema->integer()
                ->description('Scope to one car. Omit (along with user_id) to see your own recent entries across all cars.'),
            'gas_station' => $schema->string()
                ->description('Filter by gas station name (partial match).'),
            'user_id' => $schema->integer()
                ->description('Filter to entries logged by a specific user.'),
            'limit' => $schema->integer()
                ->description('Max entries to return (1-50). Defaults to 10.')
                ->default(10),
        ];
    }
}
