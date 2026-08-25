<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fuel_entries', function (Blueprint $table) {
            // Nullable, no default: existing rows have no sane fuel
            // type/variant to backfill. Requiredness on create/edit is
            // enforced at the application layer only (Filament form +
            // FuelEntry::validationRules()), not here.
            $table->string('fuel_type')->nullable()->after('car_id');
            $table->string('fuel_variant')->nullable()->after('fuel_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_entries', function (Blueprint $table) {
            $table->dropColumn(['fuel_type', 'fuel_variant']);
        });
    }
};
