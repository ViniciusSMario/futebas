<?php

/*
|--------------------------------------------------------------------------
| Recuperação de senha
|--------------------------------------------------------------------------
|
| O que a tela "Esqueci minha senha" responde depois de enviar o formulário.
| Vem por `session('status')` já traduzido — é frase, e não chave, e é por
| isso que `App\Support\Flash::resolve()` devolve null para ela em vez de
| tentar virar toast.
|
*/

return [

    'reset' => 'Sua senha foi redefinida.',
    'sent' => 'Enviamos o link de recuperação para o seu e-mail.',
    'throttled' => 'Espere um pouco antes de tentar de novo.',
    'token' => 'Este link de recuperação é inválido ou já expirou.',
    'user' => 'Não encontramos ninguém com esse e-mail.',

];
