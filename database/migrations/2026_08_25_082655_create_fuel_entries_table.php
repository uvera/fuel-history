<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fuel_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('gas_station');
            $table->decimal('litres_filled', 8, 3);
            $table->string('currency', 3)->default('RSD');
            $table->decimal('amount', 10, 2);
            $table->unsignedInteger('odometer_at_fill');
            $table->dateTime('date_of_filling');
            $table->boolean('is_partial_fill')->default(false);
            $table->decimal('trip_km', 8, 1)->nullable();
            $table->timestamps();

            $table->index(['car_id', 'date_of_filling']);
            $table->index(['car_id', 'odometer_at_fill']);
        });

        // Postgres-specific CHECK constraints (Laravel's Blueprint has no
        // fluent equivalent, and SQLite doesn't support adding CHECK via
        // ALTER TABLE at all — only at CREATE TABLE time) — data
        // integrity for values that must never be violated regardless of
        // application-layer bugs. The test suite runs against in-memory
        // SQLite for speed, so these are skipped there; app-layer
        // validation (Filament form rules, Eloquent) is still the
        // primary guarantee tests exercise — these are defense in depth
        // for the real (Postgres) database only.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fuel_entries ADD CONSTRAINT fuel_entries_litres_filled_positive CHECK (litres_filled > 0)');
            DB::statement('ALTER TABLE fuel_entries ADD CONSTRAINT fuel_entries_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE fuel_entries ADD CONSTRAINT fuel_entries_odometer_non_negative CHECK (odometer_at_fill >= 0)');
            DB::statement("ALTER TABLE fuel_entries ADD CONSTRAINT fuel_entries_currency_rsd CHECK (currency = 'RSD')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_entries');
    }
};
