@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-pitch-800 border-pitch-700 text-white placeholder-pitch-500 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm disabled:opacity-50']) }}>
