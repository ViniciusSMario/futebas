<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partida e pelada passam a guardar o estado, além da cidade.
 *
 * Faltava o par: cidade sozinha é ambígua (existe Bom Jesus em nove
 * estados) e, sem UF, o campo não tem como ser um select. O SOS já sentia
 * isso — ele decidia quem avisar usando o estado do *organizador* como
 * aproximação do estado da partida, o que erra sempre que alguém organiza
 * fora de casa.
 *
 * Preenche o histórico com o estado do organizador, que é exatamente a
 * aproximação que o app vinha usando: ninguém fica pior do que estava.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('state', 2)->nullable()->after('city');
        });

        Schema::table('game_series', function (Blueprint $table) {
            $table->string('state', 2)->nullable()->after('city');
        });

        DB::statement('update games set state = (select state from users where users.id = games.user_id) where state is null');
        DB::statement('update game_series set state = (select state from users where users.id = game_series.user_id) where state is null');
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('state');
        });

        Schema::table('game_series', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
