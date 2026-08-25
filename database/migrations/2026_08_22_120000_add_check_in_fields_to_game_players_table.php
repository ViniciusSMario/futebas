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
            // Self-declared "I'm going today", made by the participant
            // themselves during the check-in window.
            $table->timestamp('checked_in_at')->nullable();
            // The organizer's own record that someone didn't turn up —
            // deliberately separate from the check-in, which nobody but the
            // participant can write.
            $table->boolean('no_show')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'no_show']);
        });
    }
};
