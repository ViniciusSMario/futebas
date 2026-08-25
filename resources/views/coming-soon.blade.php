<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$title" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-empty-state icon="heroicon-o-wrench-screwdriver" :title="$title" :description="__('Essa funcionalidade ainda está em construção. Em breve por aqui!')">
                <x-slot name="action">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                        {{ __('Voltar ao início') }}
                    </a>
                </x-slot>
            </x-empty-state>
        </div>
    </div>
</x-app-layout>
