<?php

/*
|--------------------------------------------------------------------------
| Mensagens de validação
|--------------------------------------------------------------------------
|
| Sem este arquivo o app não mostrava inglês: mostrava a *chave*. Todo erro
| de formulário, em toda tela, saía como `validation.required` embaixo do
| campo — porque `fallback_locale` também é pt_BR, então não havia inglês
| para onde cair.
|
| `attributes` é a metade que faz a frase soar como gente: sem ele a
| mensagem diz "O campo team_name é obrigatório".
|
*/

return [

    'accepted' => 'O campo :attribute deve ser aceito.',
    'accepted_if' => 'O campo :attribute deve ser aceito quando :other for :value.',
    'active_url' => 'O campo :attribute deve conter uma URL válida.',
    'after' => 'O campo :attribute deve conter uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute deve conter uma data posterior ou igual a :date.',
    'alpha' => 'O campo :attribute deve conter apenas letras.',
    'alpha_dash' => 'O campo :attribute deve conter apenas letras, números, hífens e sublinhados.',
    'alpha_num' => 'O campo :attribute deve conter apenas letras e números.',
    'any_of' => 'O campo :attribute é inválido.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'array_keys' => 'O campo :attribute deve conter apenas estas chaves: :values.',
    'ascii' => 'O campo :attribute deve conter apenas caracteres alfanuméricos e símbolos de um byte.',
    'base64' => 'O campo :attribute deve conter um valor válido em base64.',
    'before' => 'O campo :attribute deve conter uma data anterior a :date.',
    'before_or_equal' => 'O campo :attribute deve conter uma data anterior ou igual a :date.',
    'between' => [
        'array' => 'O campo :attribute deve ter entre :min e :max itens.',
        'file' => 'O arquivo :attribute deve ter entre :min e :max kilobytes.',
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string' => 'O campo :attribute deve ter entre :min e :max caracteres.',
    ],
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'can' => 'O campo :attribute contém um valor não autorizado.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'contains' => 'O campo :attribute está sem um valor obrigatório.',
    'current_password' => 'A senha está incorreta.',
    'date' => 'O campo :attribute deve conter uma data válida.',
    'date_equals' => 'O campo :attribute deve conter uma data igual a :date.',
    'date_format' => 'O campo :attribute deve corresponder ao formato :format.',
    'decimal' => 'O campo :attribute deve ter :decimal casas decimais.',
    'declined' => 'O campo :attribute deve ser recusado.',
    'declined_if' => 'O campo :attribute deve ser recusado quando :other for :value.',
    'different' => 'Os campos :attribute e :other devem ser diferentes.',
    'digits' => 'O campo :attribute deve ter :digits dígitos.',
    'digits_between' => 'O campo :attribute deve ter entre :min e :max dígitos.',
    'dimensions' => 'O campo :attribute tem dimensões de imagem inválidas.',
    'distinct' => 'O campo :attribute tem um valor duplicado.',
    'doesnt_contain' => 'O campo :attribute não deve conter nenhum destes valores: :values.',
    'doesnt_end_with' => 'O campo :attribute não deve terminar com: :values.',
    'doesnt_start_with' => 'O campo :attribute não deve começar com: :values.',
    'email' => 'O campo :attribute deve conter um e-mail válido.',
    'encoding' => 'O campo :attribute deve usar a codificação :encoding.',
    'ends_with' => 'O campo :attribute deve terminar com: :values.',
    'enum' => 'O valor selecionado em :attribute é inválido.',
    'exists' => 'O valor selecionado em :attribute é inválido.',
    'extensions' => 'O campo :attribute deve ter uma destas extensões: :values.',
    'file' => 'O campo :attribute deve conter um arquivo.',
    'filled' => 'O campo :attribute deve ter um valor.',
    'gt' => [
        'array' => 'O campo :attribute deve ter mais de :value itens.',
        'file' => 'O arquivo :attribute deve ter mais de :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser maior que :value.',
        'string' => 'O campo :attribute deve ter mais de :value caracteres.',
    ],
    'gte' => [
        'array' => 'O campo :attribute deve ter :value itens ou mais.',
        'file' => 'O arquivo :attribute deve ter :value kilobytes ou mais.',
        'numeric' => 'O campo :attribute deve ser maior ou igual a :value.',
        'string' => 'O campo :attribute deve ter :value caracteres ou mais.',
    ],
    'hex_color' => 'O campo :attribute deve conter uma cor hexadecimal válida.',
    'image' => 'O campo :attribute deve conter uma imagem.',
    'in' => 'O valor selecionado em :attribute é inválido.',
    'in_array' => 'O campo :attribute deve existir em :other.',
    'in_array_keys' => 'O campo :attribute deve conter ao menos uma destas chaves: :values.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'ip' => 'O campo :attribute deve conter um endereço de IP válido.',
    'ipv4' => 'O campo :attribute deve conter um endereço de IPv4 válido.',
    'ipv6' => 'O campo :attribute deve conter um endereço de IPv6 válido.',
    'json' => 'O campo :attribute deve conter um JSON válido.',
    'list' => 'O campo :attribute deve ser uma lista.',
    'lowercase' => 'O campo :attribute deve estar em minúsculas.',
    'lt' => [
        'array' => 'O campo :attribute deve ter menos de :value itens.',
        'file' => 'O arquivo :attribute deve ter menos de :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser menor que :value.',
        'string' => 'O campo :attribute deve ter menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'O campo :attribute deve ter :value itens ou menos.',
        'file' => 'O arquivo :attribute deve ter :value kilobytes ou menos.',
        'numeric' => 'O campo :attribute deve ser menor ou igual a :value.',
        'string' => 'O campo :attribute deve ter :value caracteres ou menos.',
    ],
    'mac_address' => 'O campo :attribute deve conter um endereço MAC válido.',
    'max' => [
        'array' => 'O campo :attribute deve ter no máximo :max itens.',
        'file' => 'O arquivo :attribute deve ter no máximo :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute deve ter no máximo :max caracteres.',
    ],
    'max_digits' => 'O campo :attribute deve ter no máximo :max dígitos.',
    'mimes' => 'O campo :attribute deve conter um arquivo do tipo: :values.',
    'mimetypes' => 'O campo :attribute deve conter um arquivo do tipo: :values.',
    'min' => [
        'array' => 'O campo :attribute deve ter no mínimo :min itens.',
        'file' => 'O arquivo :attribute deve ter no mínimo :min kilobytes.',
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'string' => 'O campo :attribute deve ter no mínimo :min caracteres.',
    ],
    'min_digits' => 'O campo :attribute deve ter no mínimo :min dígitos.',
    'missing' => 'O campo :attribute não deve ser enviado.',
    'missing_if' => 'O campo :attribute não deve ser enviado quando :other for :value.',
    'missing_unless' => 'O campo :attribute não deve ser enviado, a menos que :other seja :value.',
    'missing_with' => 'O campo :attribute não deve ser enviado quando :values estiver presente.',
    'missing_with_all' => 'O campo :attribute não deve ser enviado quando :values estiverem presentes.',
    'multiple_of' => 'O campo :attribute deve ser um múltiplo de :value.',
    'not_in' => 'O valor selecionado em :attribute é inválido.',
    'not_regex' => 'O formato do campo :attribute é inválido.',
    'numeric' => 'O campo :attribute deve conter um número.',
    'password' => [
        'letters' => 'A senha deve conter ao menos uma letra.',
        'mixed' => 'A senha deve conter ao menos uma letra maiúscula e uma minúscula.',
        'numbers' => 'A senha deve conter ao menos um número.',
        'symbols' => 'A senha deve conter ao menos um símbolo.',
        'uncompromised' => 'Esta senha apareceu em um vazamento de dados. Escolha outra.',
    ],
    'present' => 'O campo :attribute deve estar presente.',
    'present_if' => 'O campo :attribute deve estar presente quando :other for :value.',
    'present_unless' => 'O campo :attribute deve estar presente, a menos que :other seja :value.',
    'present_with' => 'O campo :attribute deve estar presente quando :values estiver presente.',
    'present_with_all' => 'O campo :attribute deve estar presente quando :values estiverem presentes.',
    'prohibited' => 'O campo :attribute não pode ser enviado.',
    'prohibited_if' => 'O campo :attribute não pode ser enviado quando :other for :value.',
    'prohibited_if_accepted' => 'O campo :attribute não pode ser enviado quando :other for aceito.',
    'prohibited_if_declined' => 'O campo :attribute não pode ser enviado quando :other for recusado.',
    'prohibited_unless' => 'O campo :attribute não pode ser enviado, a menos que :other seja :values.',
    'prohibits' => 'O campo :attribute impede que :other seja enviado.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'required_array_keys' => 'O campo :attribute deve conter as chaves: :values.',
    'required_if' => 'O campo :attribute é obrigatório quando :other for :value.',
    'required_if_accepted' => 'O campo :attribute é obrigatório quando :other for aceito.',
    'required_if_declined' => 'O campo :attribute é obrigatório quando :other for recusado.',
    'required_unless' => 'O campo :attribute é obrigatório, a menos que :other seja :values.',
    'required_with' => 'O campo :attribute é obrigatório quando :values estiver presente.',
    'required_with_all' => 'O campo :attribute é obrigatório quando :values estiverem presentes.',
    'required_without' => 'O campo :attribute é obrigatório quando :values não estiver presente.',
    'required_without_all' => 'O campo :attribute é obrigatório quando nenhum destes estiver presente: :values.',
    'same' => 'Os campos :attribute e :other devem ser iguais.',
    'size' => [
        'array' => 'O campo :attribute deve conter :size itens.',
        'file' => 'O arquivo :attribute deve ter :size kilobytes.',
        'numeric' => 'O campo :attribute deve ser :size.',
        'string' => 'O campo :attribute deve ter :size caracteres.',
    ],
    'starts_with' => 'O campo :attribute deve começar com: :values.',
    'string' => 'O campo :attribute deve ser um texto.',
    'timezone' => 'O campo :attribute deve conter um fuso horário válido.',
    'unique' => 'Este :attribute já está em uso.',
    'uploaded' => 'Não foi possível enviar o arquivo :attribute.',
    'uppercase' => 'O campo :attribute deve estar em maiúsculas.',
    'url' => 'O campo :attribute deve conter uma URL válida.',
    'ulid' => 'O campo :attribute deve conter um ULID válido.',
    'uuid' => 'O campo :attribute deve conter um UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensagens sob medida
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'password' => [
            'confirmed' => 'As senhas não conferem.',
        ],
        'photo' => [
            'max' => 'A foto deve ter no máximo 2 MB.',
            'image' => 'O arquivo enviado precisa ser uma imagem.',
        ],
        'positions' => [
            'required' => 'Escolha ao menos uma posição.',
        ],
        'modalities' => [
            'required' => 'Escolha ao menos uma modalidade.',
        ],
        'days' => [
            'required' => 'Escolha ao menos um dia da semana.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nomes dos campos
    |--------------------------------------------------------------------------
    |
    | Sem isto a mensagem sai com o nome da coluna: "O campo team_name é
    | obrigatório". São os campos que existem nos formulários deste app.
    |
    */

    'attributes' => [
        // Conta
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'password_confirmation' => 'confirmação da senha',
        'current_password' => 'senha atual',
        'phone' => 'telefone',
        'photo' => 'foto',
        'role' => 'perfil',
        'birth_date' => 'data de nascimento',

        // Local
        'state' => 'estado',
        'city' => 'cidade',
        'location' => 'local',

        // Partida
        'team_name' => 'nome da partida',
        'game_id' => 'partida',
        'date' => 'data',
        'start_time' => 'horário de início',
        'end_time' => 'horário de término',
        'weekday' => 'dia da semana',
        'modality' => 'modalidade',
        'modalities' => 'modalidades',
        'max_players' => 'máximo de jogadores',
        'price' => 'valor',
        'description' => 'descrição',
        'requires_approval' => 'aprovação manual',
        'teams_count' => 'quantidade de times',

        // Jogador
        'position' => 'posição',
        'position_primary' => 'posição principal',
        'positions' => 'posições',
        'level' => 'nível',
        'price_per_game' => 'valor por partida',
        'price_per_game_outside' => 'valor fora da cidade',
        'plays_outside_city' => 'atua fora da cidade',
        'days' => 'dias da semana',
        'availability' => 'disponibilidade',

        // SOS e avaliações
        'offered_value' => 'valor oferecido',
        'asking_price' => 'valor pedido',
        'expires_at' => 'prazo',
        'message' => 'mensagem',
        'rating' => 'nota',
        'comment' => 'comentário',
        'reason' => 'justificativa',

        // Financeiro
        'amount_due' => 'valor devido',
        'payment_status' => 'situação do pagamento',
        'plan' => 'plano',
    ],

];
