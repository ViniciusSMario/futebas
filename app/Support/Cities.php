<?php

namespace App\Support;

use App\Models\PlayerProfile;

/**
 * Os municípios brasileiros, por estado.
 *
 * A lista vem do IBGE mas mora **dentro do projeto**
 * (`resources/data/municipios.json`), e não de uma chamada à API deles. Dois
 * motivos: um campo de cidade que depende de um serviço externo no ar deixa
 * de dar para criar partida quando a rede oscila — e este app é usado no
 * celular, na beira do campo; e só tendo a lista aqui dá para o servidor
 * conferir se a cidade recebida existe mesmo naquele estado, em vez de
 * confiar no que o navegador mandou.
 *
 * Para atualizar (raro — municípios novos são acontecimento de década),
 * regere o JSON a partir de
 * `https://servicodados.ibge.gov.br/api/v1/localidades/municipios`.
 */
class Cities
{
    /** @var array<string, array<int, string>>|null */
    private static ?array $catalog = null;

    /**
     * Os estados, na forma "UF" => "Nome".
     *
     * @return array<string, string>
     */
    public static function states(): array
    {
        return PlayerProfile::STATES;
    }

    /**
     * Os municípios de um estado, em ordem alfabética.
     *
     * @return array<int, string>
     */
    public static function for(?string $uf): array
    {
        if (blank($uf)) {
            return [];
        }

        return self::catalog()[mb_strtoupper($uf)] ?? [];
    }

    /**
     * Esta cidade existe neste estado?
     *
     * A comparação ignora caixa porque o valor pode chegar de um endereço
     * digitado à mão (um link de busca compartilhado, por exemplo), não só
     * do select.
     */
    public static function has(?string $uf, ?string $city): bool
    {
        return self::canonical($uf, $city) !== null;
    }

    /**
     * O nome do município exatamente como o IBGE o escreve, ou `null` se
     * ele não existe naquele estado.
     */
    public static function canonical(?string $uf, ?string $city): ?string
    {
        if (blank($city)) {
            return null;
        }

        $needle = mb_strtolower(trim((string) $city));

        foreach (self::for($uf) as $name) {
            if (mb_strtolower($name) === $needle) {
                return $name;
            }
        }

        return null;
    }

    public static function isState(?string $uf): bool
    {
        return filled($uf) && array_key_exists(mb_strtoupper((string) $uf), self::catalog());
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function catalog(): array
    {
        // Lido uma vez por processo. São ~135 KB de JSON: barato o
        // suficiente para não justificar uma tabela, e imutável o
        // suficiente para não justificar cache.
        return self::$catalog ??= (array) json_decode(
            (string) file_get_contents(resource_path('data/municipios.json')),
            true,
        );
    }
}
