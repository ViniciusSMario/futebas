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
            $table->decimal('average_rating', 3, 2)->nullable()->after('price_per_game_outside');
            $table->decimal('average_punctuality', 3, 2)->nullable()->after('average_rating');
            $table->decimal('average_behavior', 3, 2)->nullable()->after('average_punctuality');
            $table->decimal('average_performance', 3, 2)->nullable()->after('average_behavior');
            $table->unsignedInteger('ratings_count')->default(0)->after('average_performance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            $table->dropColumn(['average_rating', 'average_punctuality', 'average_behavior', 'average_performance', 'ratings_count']);
        });
    }
};
