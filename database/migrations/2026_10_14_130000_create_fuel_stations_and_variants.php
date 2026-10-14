<?php

use App\Services\FuelCatalog;
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
        Schema::create('fuel_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_key')->unique();
            $table->timestamps();
        });

        Schema::create('fuel_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_station_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('name_key');
            $table->timestamps();

            $table->unique(['fuel_station_id', 'name_key']);
        });

        Schema::table('fuel_entries', function (Blueprint $table) {
            $table->foreignId('fuel_station_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('fuel_variant_id')->nullable()->constrained()->restrictOnDelete();
        });

        app(FuelCatalog::class)->resolveFromFuelEntries();

        Schema::table('fuel_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('fuel_station_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fuel_variant_id');
            $table->dropConstrainedForeignId('fuel_station_id');
        });

        Schema::dropIfExists('fuel_variants');
        Schema::dropIfExists('fuel_stations');
    }
};
