<?php

namespace App\Providers;

use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // RSD figures read naturally in Serbian numeric convention: dot for
        // thousands, comma for decimals (e.g. 64.879,74).
        Table::configureUsing(fn (Table $table) => $table->defaultNumberLocale('sr'));
    }
}
