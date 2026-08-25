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
        Schema::table('game_players', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignId('guest_player_id')->nullable()->after('user_id')->constrained('guest_players')->cascadeOnDelete();

            $table->unique(['game_id', 'guest_player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropUnique(['game_id', 'guest_player_id']);
            $table->dropConstrainedForeignId('guest_player_id');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
