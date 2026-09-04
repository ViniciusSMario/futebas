@props([
    // Nomes dos campos enviados no formulário.
    'stateName' => 'state',
    'name' => 'city',
    // Valores atuais (já resolvidos com old() por quem chama).
    'state' => null,
    'city' => null,
    'required' => false,
    'label' => null,
    'stateLabel' => null,
    // Modo filtro: "qualquer estado" / "qualquer cidade" são respostas
    // válidas numa busca, ao contrário de um cadastro.
    'any' => false,
    // Prefixo dos ids, para dois pares conviverem na mesma página.
    'id' => null,
])

@php
    $cityId = $id ? $id.'-city' : $name;
    $stateId = $id ? $id.'-state' : $stateName;

    $selectedState = filled($state) ? mb_strtoupper((string) $state) : '';
    $cities = \App\Support\Cities::for($selectedState);
    $cityPlaceholder = $any ? __('Qualquer cidade') : __('Selecione...');

    $selectClass = 'mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm';
@endphp

{{-- Duas células irmãs, e não um bloco só: elas entram na grade de quem
     chama do mesmo jeito que qualquer outro campo do formulário. --}}
<div>
    <x-input-label :for="$stateId" :value="$stateLabel ?? __('Estado')" />
    <select
        id="{{ $stateId }}"
        name="{{ $stateName }}"
        data-state-select="{{ $cityId }}"
        @required($required)
        class="{{ $selectClass }}"
    >
        <option value="">{{ $any ? __('Qualquer estado') : __('Selecione...') }}</option>
        @foreach (\App\Support\Cities::states() as $uf => $stateLabelOption)
            <option value="{{ $uf }}" @selected($selectedState === $uf)>{{ $uf }} — {{ $stateLabelOption }}</option>
        @endforeach
    </select>
    <x-input-error class="mt-2" :messages="$errors->get($stateName)" />
</div>

<div>
    <x-input-label :for="$cityId" :value="$label ?? __('Cidade')" />
    {{-- As opções do estado escolhido já vêm prontas do servidor: sem
         piscar "carregando" e sem depender do JS para o caso mais comum,
         que é o formulário abrir com o estado de quem está usando. Trocar
         de estado aí sim busca a lista nova em /cidades/{uf}. --}}
    <select
        id="{{ $cityId }}"
        name="{{ $name }}"
        data-selected="{{ $city }}"
        data-placeholder="{{ $cityPlaceholder }}"
        @required($required)
        class="{{ $selectClass }}"
    >
        <option value="">{{ $cities === [] && ! $any ? __('Selecione o estado primeiro') : $cityPlaceholder }}</option>
        @foreach ($cities as $cityOption)
            <option value="{{ $cityOption }}" @selected($city === $cityOption)>{{ $cityOption }}</option>
        @endforeach
    </select>
    <x-input-error class="mt-2" :messages="$errors->get($name)" />

    {{-- Espaço para o que pertence ao campo de cidade e não ao formulário
         inteiro — hoje, o filtro de cidades vizinhas na busca. --}}
    {{ $slot }}
</div>
