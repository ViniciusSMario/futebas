<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `plan` aqui é cópia: a verdade é a assinatura, que sabe de datas e
 * status. A coluna existe porque a busca de jogadores ordena por ela — o
 * destaque do Pro é um `order by`, e um `order by` não pode depender de
 * regra em PHP — exatamente como as médias de avaliação e presença.
 *
 * Quem a mantém em dia é App\Models\Subscription, a cada gravação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan')->default('free')->index()->after('role');
            $table->string('stripe_customer_id')->nullable()->unique()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['plan', 'stripe_customer_id']);
        });
    }
};
