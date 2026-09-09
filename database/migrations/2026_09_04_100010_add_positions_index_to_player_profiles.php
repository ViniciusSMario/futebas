<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índice para o filtro de posição — o mais usado da busca, e o único que
 * nenhum índice comum alcança.
 *
 * `player_profiles.positions` é uma coluna JSON e a busca pergunta por ela
 * com `whereJsonContains`, que vira `json_contains(positions, '"Goleiro"')`.
 * Um índice B-tree não serve para isso: o valor procurado está dentro de um
 * array. O MySQL 8.0.17 resolveu esse caso com índice multivalorado, que
 * indexa cada elemento do array e é exatamente o que `json_contains` sabe
 * consultar.
 *
 * Vive numa migration separada de propósito. É a única DDL do projeto que
 * depende de motor e de versão, então quando ela não puder rodar — SQLite
 * dos testes, MariaDB, MySQL antigo — o que se perde é uma otimização, e os
 * índices comuns da migration anterior já estão aplicados. Sem isso, uma
 * instalação em MariaDB travaria no meio da fila de migrations.
 *
 * `char(32)` cabe com folga: as posições vêm de `PlayerProfile::POSITIONS`,
 * validadas com `Rule::in`, e a mais longa tem oito letras.
 */
return new class extends Migration
{
    private const INDEX = 'player_profiles_positions_index';

    public function up(): void
    {
        if (! $this->supportsMultiValuedIndex()) {
            return;
        }

        DB::statement(
            'create index '.self::INDEX.' on player_profiles ((cast(positions->\'$\' as char(32) array)))'
        );
    }

    public function down(): void
    {
        if (! $this->supportsMultiValuedIndex()) {
            return;
        }

        DB::statement('drop index '.self::INDEX.' on player_profiles');
    }

    /**
     * MySQL 8.0.17 ou mais novo. O MariaDB precisa ser recusado por nome:
     * ele se anuncia como "10.x", que passaria por qualquer comparação de
     * versão contra 8.0.17 sem suportar o recurso.
     */
    private function supportsMultiValuedIndex(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $version = (string) DB::selectOne('select version() as v')->v;

        if (str_contains(strtolower($version), 'mariadb')) {
            return false;
        }

        return version_compare($version, '8.0.17', '>=');
    }
};
