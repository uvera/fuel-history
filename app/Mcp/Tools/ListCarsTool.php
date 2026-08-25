<?php

namespace App\Mcp\Tools;

use App\Models\Car;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list-cars')]
#[Description('Lists the cars in the fuel-tracking app, so you can resolve a car name to its ID before adding, editing, or listing fuel entries.')]
class ListCarsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $includeArchived = (bool) $request->get('include_archived', false);

        $cars = Car::query()
            ->when(! $includeArchived, fn ($query) => $query->active())
            ->orderBy('name')
            ->get(['id', 'name', 'make', 'model', 'year', 'plate', 'archived_at']);

        $payload = $cars->map(fn (Car $car): array => [
            'id' => $car->id,
            'name' => $car->name,
            'make' => $car->make,
            'model' => $car->model,
            'year' => $car->year,
            'plate' => $car->plate,
            'archived' => $car->isArchived(),
        ])->all();

        return Response::text(json_encode($payload, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_archived' => $schema->boolean()
                ->description('Include archived cars in the results. Defaults to false.')
                ->default(false),
        ];
    }
}
