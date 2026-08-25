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
        Schema::table('games', function (Blueprint $table) {
            // Nullable, and nulled rather than cascaded on delete: a
            // one-off match has no series, and ending a series must never
            // take its already-played matches down with it.
            $table->foreignId('game_series_id')->nullable()->after('user_id')->constrained()->nullOnDelete();

            // One occurrence per date per series. Standalone games all
            // carry a null series, which unique indexes treat as distinct,
            // so they are unaffected.
            $table->unique(['game_series_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['game_series_id', 'date']);
            $table->dropConstrainedForeignId('game_series_id');
        });
    }
};
