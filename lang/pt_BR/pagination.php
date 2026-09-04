<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Linhas da paginação
    |--------------------------------------------------------------------------
    |
    | A view de paginação usa chaves com ponto (`pagination.previous`), que só
    | um arquivo de idioma resolve - ao contrário do resto do app, onde a
    | própria frase em português é a chave. Sem este arquivo, e com
    | `locale` e `fallback_locale` ambos em pt_BR, o botão da busca de
    | jogadores imprimia literalmente "pagination.next".
    |
    */

    'previous' => '&laquo; Anterior',
    'next' => 'Próxima &raquo;',

];
