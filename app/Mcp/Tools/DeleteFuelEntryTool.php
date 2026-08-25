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

#[Name('delete-fuel-entry')]
#[Description('Deletes a fuel entry. Only the entry\'s owner or an admin may delete it -- same rule as the app itself. This cannot be undone.')]
class DeleteFuelEntryTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'entry_id' => ['required', 'integer'],
        ]);

        $entry = FuelEntry::find($validated['entry_id']);

        if ($entry === null) {
            return Response::error("Fuel entry #{$validated['entry_id']} does not exist.");
        }

        // Reuses FuelEntryPolicy::delete exactly -- owner or admin.
        if (! $request->user()->can('delete', $entry)) {
            return Response::error('You can only delete fuel entries you logged yourself, unless you are an admin.');
        }

        $entryId = $entry->id;
        $entry->delete();

        return Response::text("Fuel entry #{$entryId} deleted.");
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'entry_id' => $schema->integer()
                ->description('The fuel entry to delete. Use list-fuel-entries to find it.')
                ->required(),
        ];
    }
}
