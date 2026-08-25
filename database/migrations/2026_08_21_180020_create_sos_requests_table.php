<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->string('position')->default('Goleiro');
            $table->decimal('offered_value', 8, 2);
            $table->text('message')->nullable();
            $table->string('status')->default('open');
            // Set when the organizer picks a candidate; the winning
            // application also carries status = accepted.
            $table->unsignedBigInteger('accepted_application_id')->nullable();
            $table->unsignedInteger('notified_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index('organizer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_requests');
    }
};
