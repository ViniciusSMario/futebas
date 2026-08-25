<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // What the goalkeeper wants to be paid: usually the offered
            // value, but they may counter-offer.
            $table->decimal('asking_price', 8, 2);
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // One candidacy per goalkeeper per request.
            $table->unique(['sos_request_id', 'user_id']);
            $table->index(['sos_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_applications');
    }
};
