<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para as duas buscas do app.
 *
 * Nenhuma delas tinha um índice sequer: filtrar goleiro em Teresina lia a
 * tabela inteira e ordenava na memória. Com poucas dezenas de jogadores
 * ninguém percebe, e é justamente por isso que o problema só apareceria
 * quando já houvesse gente demais para consertar com calma.
 *
 * Cada índice aqui corresponde a um `where` que existe de verdade nos
 * controllers — índice que ninguém consulta é escrita mais lenta em troca
 * de nada. Ficou de fora, de propósito, a ordenação da busca de jogadores:
 * `PlayerController::applyHighlight()` ordena primeiro por um `case` sobre o
 * plano, e uma expressão não indexável na primeira posição do `order by`
 * impede o uso de índice para ordenar seja lá o que vier depois. É o preço
 * do destaque de quem assina, e é uma decisão, não um esquecimento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            // O filtro que mais reduz resultado: ninguém procura jogador
            // fora da própria região. Estado antes de cidade porque a busca
            // aceita só o estado, mas nunca a cidade sozinha.
            $table->index(['state', 'city'], 'player_profiles_region_index');

            // Faixa de preço.
            $table->index('price_per_game', 'player_profiles_price_index');
        });

        Schema::table('games', function (Blueprint $table) {
            // Os dois filtros que toda busca de partida aplica —
            // `status = open` e a data à frente — e, na mesma ordem, a
            // coluna pela qual o resultado sai ordenado.
            $table->index(['status', 'date'], 'games_status_date_index');

            $table->index(['state', 'city'], 'games_region_index');
        });

        Schema::table('availabilities', function (Blueprint $table) {
            // O filtro "quem joga na quarta" vira um `exists` por jogador.
            // A chave estrangeira já indexa `user_id`; o dia junto evita ler
            // as linhas para descartar quase todas.
            $table->index(['user_id', 'day_of_week'], 'availabilities_user_day_index');
        });
    }

    public function down(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            $table->dropIndex('player_profiles_region_index');
            $table->dropIndex('player_profiles_price_index');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex('games_status_date_index');
            $table->dropIndex('games_region_index');
        });

        Schema::table('availabilities', function (Blueprint $table) {
            $table->dropIndex('availabilities_user_day_index');
        });
    }
};
