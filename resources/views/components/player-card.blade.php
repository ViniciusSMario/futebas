@props(['playerProfile'])

@php
    $positionAbbreviations = [
        'Goleiro' => 'GOL',
        'Zagueiro' => 'ZAG',
        'Lateral' => 'LAT',
        'Volante' => 'VOL',
        'Meia' => 'MEI',
        'Atacante' => 'ATA',
    ];

    $modalityAbbreviations = [
        'Society' => 'SOC',
        'Futsal' => 'FUT',
        'Campo' => 'CAM',
        'Outros' => 'OUT',
    ];

    $levelScores = [
        'Iniciante' => 62,
        'Recreativo' => 72,
        'Intermediário' => 80,
        'Avançado' => 88,
    ];

    $user = $playerProfile->user;
    $primaryPosition = $playerProfile->positions[0] ?? null;
    $primaryModality = $playerProfile->modalities[0] ?? null;

    $posLabel = $positionAbbreviations[$primaryPosition] ?? Str::upper(Str::limit($primaryPosition ?? '—', 3, ''));
    $modLabel = $modalityAbbreviations[$primaryModality] ?? Str::upper(Str::limit($primaryModality ?? '—', 3, ''));
    $levelScore = $levelScores[$playerProfile->level] ?? 70;

    $toScore = fn (?string $average) => $average !== null ? max(1, min(99, (int) round($average * 20))) : null;

    $overall = $playerProfile->ratings_count > 0
        ? max(1, min(99, (int) round($playerProfile->average_rating * 20)))
        : $levelScore;

    $stats = [
        ['label' => __('PON'), 'value' => $toScore($playerProfile->average_punctuality)],
        ['label' => __('NIV'), 'value' => $levelScore],
        ['label' => __('COM'), 'value' => $toScore($playerProfile->average_behavior)],
        ['label' => __('JOG'), 'value' => $playerProfile->ratings_count],
        ['label' => __('DES'), 'value' => $toScore($playerProfile->average_performance)],
        ['label' => __('VLR'), 'value' => 'R$'.number_format((float) $playerProfile->price_per_game, 0, ',', '.')],
    ];

    $shieldClip = 'polygon(50% 0%, 54% 2.5%, 58% 3%, 88% 3%, 91% 4%, 94% 6%, 96% 8%, 97% 10.5%, 97% 30%, 96.5% 50%, 95.5% 68%, 94% 78%, 91% 85%, 86% 90.5%, 79% 95%, 70% 98%, 60% 99.6%, 50% 100%, 40% 99.6%, 30% 98%, 21% 95%, 14% 90.5%, 9% 85%, 6% 78%, 4.5% 68%, 3.5% 50%, 3% 30%, 3% 10.5%, 4% 8%, 6% 6%, 9% 4%, 12% 3%, 42% 3%, 46% 2.5%)';

    $nameLength = Str::length($user->name);
    $nameSizeClass = match (true) {
        $nameLength > 22 => 'text-sm sm:text-base',
        $nameLength > 16 => 'text-base sm:text-lg',
        default => 'text-lg sm:text-2xl',
    };
@endphp

<div {{ $attributes->merge(['class' => 'w-64 sm:w-72 mx-auto']) }}>
    <div class="p-[5px] shadow-xl shadow-black/40" style="clip-path: {{ $shieldClip }}; background: linear-gradient(155deg, #fde68a 0%, #d4a72c 18%, #fbbf24 32%, #92650f 46%, #fde68a 60%, #b8860b 78%, #fde68a 100%);">
        <div class="relative aspect-[5/7] overflow-hidden" style="clip-path: {{ $shieldClip }}; background: radial-gradient(120% 90% at 50% 0%, #1c2723 0%, #0c1512 55%, #060a08 100%);">
            {{-- Photo --}}
            <div class="absolute inset-x-0 top-0 h-[62%]" style="mask-image: linear-gradient(to bottom, black 55%, transparent 96%); -webkit-mask-image: linear-gradient(to bottom, black 55%, transparent 96%);">
                @if ($playerProfile->photo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($playerProfile->photo_path) }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-pitch-700 to-pitch-800">
                        <span class="text-7xl font-black text-pitch-500">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                    </div>
                @endif
            </div>

            {{-- Overall + position --}}
            <div class="absolute top-[7%] left-[8%]">
                <p class="text-4xl sm:text-5xl font-black text-amber-200 leading-none drop-shadow">{{ $overall }}</p>
                <p class="mt-1 text-sm font-extrabold tracking-widest text-amber-200">{{ $posLabel }}</p>
                <div class="mt-2 h-0.5 w-11 bg-gradient-to-r from-amber-600 to-transparent"></div>
            </div>

            {{-- State + modality badges --}}
            <div class="absolute top-[29%] left-[8%] flex flex-col gap-2">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-amber-400/10 border border-amber-400/50 text-[11px] font-bold text-amber-200">{{ $playerProfile->state ?? '—' }}</span>
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-amber-400/10 border border-amber-400/50 text-[10px] font-bold text-amber-200">{{ $modLabel }}</span>
            </div>

            {{-- Name --}}
            <p class="absolute top-[60%] inset-x-0 text-center {{ $nameSizeClass }} font-black uppercase tracking-wide text-amber-50 truncate px-6">{{ $user->name }}</p>
            <div class="absolute top-[67%] inset-x-[16%] h-px bg-gradient-to-r from-transparent via-amber-600 to-transparent"></div>

            {{-- Stats --}}
            <div class="absolute top-[70%] inset-x-[15%] grid grid-cols-2 gap-x-5 gap-y-2.5">
                @foreach ($stats as $stat)
                    <p class="text-xs sm:text-sm font-extrabold text-amber-50 whitespace-nowrap">
                        <span class="text-amber-400 tabular-nums">{{ $stat['value'] ?? '–' }}</span>
                        {{ $stat['label'] }}
                    </p>
                @endforeach
            </div>
        </div>
    </div>
</div>
