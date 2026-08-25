<?php

use App\Mcp\Servers\FuelHistoryServer;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

// Sanctum doesn't register an 'abilities' route-middleware alias by
// default in a Laravel 11+ app (bootstrap/app.php has no such alias) --
// reference the middleware class directly rather than relying on an
// alias that may not exist, so this works regardless of alias
// registration elsewhere.
Mcp::web('/mcp/fuel-history', FuelHistoryServer::class)
    ->middleware(['auth:sanctum', CheckAbilities::class.':mcp:use']);
