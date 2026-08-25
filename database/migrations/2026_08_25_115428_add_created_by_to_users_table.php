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
        Schema::table('users', function (Blueprint $table) {
            // Nullable self-referencing FK: who created this user via the
            // panel. Null means a "root" account (CLI users:make-admin,
            // db:seed, or any pre-existing user) — see User::isRootAdmin()
            // and User::manages() for what that implies. nullOnDelete is a
            // safety net only; a `deleting` model event reparents children
            // to the deleted user's own creator before that FK would ever
            // fire, so a mid-chain delete never accidentally promotes a
            // descendant admin to root.
            $table->foreignId('created_by')
                ->nullable()
                ->after('role')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
