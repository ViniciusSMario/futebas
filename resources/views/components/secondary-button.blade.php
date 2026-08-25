<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-pitch-800 border border-pitch-700 rounded-md font-semibold text-xs text-pitch-200 uppercase tracking-widest shadow-sm hover:bg-pitch-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-pitch-900 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
