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
        Schema::table('player_profiles', function (Blueprint $table) {
            // Denormalised like the rating averages beside them, and for
            // the same reason: the player search has to be able to sort on
            // reliability without counting rows per candidate.
            $table->unsignedInteger('games_played')->default(0)->after('ratings_count');
            $table->unsignedInteger('no_shows')->default(0)->after('games_played');
            $table->unsignedInteger('cancellations')->default(0)->after('no_shows');
            // Percentage of matches they were confirmed for and turned up
            // to. Null until they have any history at all, so "no data" is
            // never shown as 0%.
            $table->decimal('attendance_rate', 5, 2)->nullable()->after('cancellations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            $table->dropColumn(['games_played', 'no_shows', 'cancellations', 'attendance_rate']);
        });
    }
};
