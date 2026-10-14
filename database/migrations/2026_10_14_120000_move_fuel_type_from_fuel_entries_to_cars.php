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
        Schema::table('cars', function (Blueprint $table) {
            $table->string('fuel_type')->nullable();
        });

        // First fill that actually has a type wins. Cars with no fills,
        // or only null types, become gasoline. Correlated subquery with
        // ORDER BY/LIMIT works on both SQLite (tests) and Postgres.
        DB::statement("
            UPDATE cars
            SET fuel_type = COALESCE((
                SELECT fuel_entries.fuel_type
                FROM fuel_entries
                WHERE fuel_entries.car_id = cars.id
                  AND fuel_entries.fuel_type IS NOT NULL
                ORDER BY fuel_entries.date_of_filling ASC, fuel_entries.id ASC
                LIMIT 1
            ), 'gasoline')
        ");

        // Nullable(false)->change() rebuilds the table on SQLite (Laravel's
        // native schema builder, same path as the odometer nullability
        // migration) and ends with a NOT NULL column and no default.
        Schema::table('cars', function (Blueprint $table) {
            $table->string('fuel_type')->nullable(false)->change();
        });

        Schema::table('fuel_entries', function (Blueprint $table) {
            $table->dropColumn('fuel_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_entries', function (Blueprint $table) {
            $table->string('fuel_type')->nullable();
        });

        DB::statement('
            UPDATE fuel_entries
            SET fuel_type = (
                SELECT cars.fuel_type
                FROM cars
                WHERE cars.id = fuel_entries.car_id
            )
        ');

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('fuel_type');
        });
    }
};
