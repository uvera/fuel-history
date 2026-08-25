<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddFuelEntryTool;
use App\Mcp\Tools\DeleteFuelEntryTool;
use App\Mcp\Tools\EditFuelEntryTool;
use App\Mcp\Tools\ListCarsTool;
use App\Mcp\Tools\ListFuelEntriesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Fuel History Server')]
#[Version('1.0.0')]
#[Instructions('Log, edit, delete, and browse fuel fill-ups for cars in the Fuel History app. Use list-cars to find a car_id before adding an entry, and list-fuel-entries to find an entry_id before editing or deleting one, or to check the last odometer reading for a car. Currency is always RSD. Editing/deleting is restricted to the entry\'s owner or an admin.')]
class FuelHistoryServer extends Server
{
    protected array $tools = [
        ListCarsTool::class,
        ListFuelEntriesTool::class,
        AddFuelEntryTool::class,
        EditFuelEntryTool::class,
        DeleteFuelEntryTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
