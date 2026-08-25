<x-filament-panels::page>
    <p class="fi-section-header-description mb-4 text-sm text-gray-500 dark:text-gray-400">
        Generate a token to use this app's data from an MCP-compatible AI client (Claude, Cursor, etc.) --
        paste the URL and Authorization header shown after generating into your client's MCP server config.
        Tokens can be revoked at any time.
    </p>

    {{ $this->table }}
</x-filament-panels::page>
