<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma linha por usuário: a assinatura atual dele, viva ou vencida.
 *
 * A tabela guarda a assinatura, não o plano — quem está no Free nunca
 * precisa de linha aqui, e quem cancelou mantém a dele com o `ends_at`
 * dizendo até quando o acesso vale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->string('status');

            // Identificadores do Stripe. Ficam nulos enquanto a cobrança não
            // estiver configurada — dá para operar planos na mão sem eles.
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('stripe_price_id')->nullable();

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_started_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            // Preenchido quando o cancelamento tem data marcada: até lá o
            // plano continua valendo.
            $table->timestamp('ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);

            $table->timestamps();

            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
