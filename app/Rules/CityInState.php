<?php

namespace App\Rules;

use App\Support\Cities;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A cidade precisa existir no estado que veio junto no formulário.
 *
 * O select já limita a escolha, mas o select é do navegador: sem esta
 * regra, um POST montado à mão gravaria "Teresina" com UF "SP" e a busca
 * por região passaria a mentir para todo mundo daquela cidade.
 */
class CityInState implements ValidationRule
{
    public function __construct(private readonly ?string $uf) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Cidade vazia ou estado inválido são problema de outra regra —
        // duas mensagens sobre o mesmo erro só confundem.
        if (blank($value) || ! Cities::isState($this->uf)) {
            return;
        }

        if (! Cities::has($this->uf, $value)) {
            $fail(__('Selecione uma cidade do estado escolhido.'));
        }
    }
}
