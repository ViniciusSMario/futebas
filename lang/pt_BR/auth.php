<?php

/*
|--------------------------------------------------------------------------
| Mensagens de autenticação
|--------------------------------------------------------------------------
|
| `auth.failed` é a frase mais vista do app inteiro depois de "Entrar", e
| saía como a própria chave: sem este arquivo o Laravel não tinha para onde
| cair, porque `fallback_locale` também é pt_BR.
|
*/

return [

    'failed' => 'E-mail ou senha não conferem.',
    'password' => 'A senha está incorreta.',
    'throttle' => 'Muitas tentativas. Tente de novo em :seconds segundos.',

];
