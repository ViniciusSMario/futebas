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
        Schema::create('player_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('photo_path')->nullable();
            $table->date('birth_date');
            $table->string('state', 2);
            $table->string('city');
            $table->string('phone');
            $table->json('positions');
            $table->json('modalities');
            $table->string('level');
            $table->decimal('price_per_game', 8, 2);
            $table->boolean('plays_outside_city')->default(false);
            $table->decimal('price_per_game_outside', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_profiles');
    }
};
