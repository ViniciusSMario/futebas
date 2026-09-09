<?php

namespace App\Http\Controllers;

use App\Support\Cities;
use Illuminate\Http\JsonResponse;

/**
 * Os municípios de um estado, para o select de cidade se preencher quando
 * alguém troca o estado.
 *
 * Rota pública porque o cadastro precisa dela antes de existir conta, e
 * porque a lista é dado público do IBGE. A resposta é imutável na prática,
 * então vai com cache longo: o navegador só busca a mesma UF uma vez.
 */
class CityController extends Controller
{
    public function index(string $uf): JsonResponse
    {
        abort_unless(Cities::isState($uf), 404);

        return response()
            ->json(Cities::for($uf))
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
