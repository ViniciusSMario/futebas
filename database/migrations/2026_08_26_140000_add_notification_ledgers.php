<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marcas de "isto já foi avisado".
 *
 * Todo aviso automático precisa de uma delas. O scheduler roda de novo a
 * cada hora e um servidor que ficou fora do ar volta com trabalho
 * acumulado: sem registro do que já saiu, a rodada seguinte manda o mesmo
 * lembrete outra vez — e lembrete repetido é o jeito mais rápido de a
 * pessoa desligar as notificações do app inteiro.
 *
 * São colunas de data, e não booleanos, porque "quando avisamos" responde
 * perguntas que "se avisamos" não responde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('reminded_24h_at')->nullable()->after('status');
            $table->timestamp('reminded_2h_at')->nullable()->after('reminded_24h_at');
        });

        Schema::table('sos_requests', function (Blueprint $table) {
            // O status continua `open` mesmo depois do prazo — quem decide
            // aquela coluna é sempre uma decisão explícita. Isto registra
            // só que os envolvidos foram avisados do vencimento.
            $table->timestamp('expiry_notified_at')->nullable()->after('expires_at');
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->timestamp('payment_reminded_at')->nullable()->after('amount_due');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['reminded_24h_at', 'reminded_2h_at']);
        });

        Schema::table('sos_requests', function (Blueprint $table) {
            $table->dropColumn('expiry_notified_at');
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('payment_reminded_at');
        });
    }
};
