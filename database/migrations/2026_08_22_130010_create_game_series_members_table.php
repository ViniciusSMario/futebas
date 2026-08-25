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
        // The "mensalistas": people who play this pelada every week and are
        // added to each occurrence as it is generated. Carries the same
        // user / guest split as game_players, since a regular may well be
        // someone with no app account.
        Schema::create('game_series_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_series_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('guest_player_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Nulls compare as distinct, so these only bite on real
            // duplicates of the same person in the same series.
            $table->unique(['game_series_id', 'user_id']);
            $table->unique(['game_series_id', 'guest_player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_series_members');
    }
};
