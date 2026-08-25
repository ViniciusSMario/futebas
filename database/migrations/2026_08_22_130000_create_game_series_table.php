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
        // The template a weekly pelada is stamped from. Mirrors the Game
        // fields an occurrence needs, with the concrete date replaced by
        // the weekday it repeats on.
        Schema::create('game_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('team_name');
            $table->string('location');
            $table->string('city');
            $table->string('modality');
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->unsignedInteger('max_players');
            $table->decimal('price', 8, 2);
            $table->json('positions');
            $table->text('description')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_series');
    }
};
