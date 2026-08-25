<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-bell" :title="__('Notificações')" :subtitle="__('Tudo que aconteceu por aqui')">
            @if ($notifications->isNotEmpty())
                <x-slot name="action">
                    <form method="post" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="text-xs font-bold uppercase tracking-widest text-pitch-400 hover:text-white transition">
                            {{ __('Marcar todas como lidas') }}
                        </button>
                    </form>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-push-toggle />

            @if ($notifications->isEmpty())
                <x-empty-state icon="heroicon-o-bell-slash" :title="__('Nada por aqui ainda')" :description="__('Convites, mudanças nas suas partidas, avaliações e chamadas de SOS aparecem nesta lista.')" />
            @else
                <div class="space-y-2">
                    @foreach ($notifications as $notification)
                        @php
                            $data = $notification->data;
                            $unread = $notification->read_at === null;
                        @endphp

                        <a
                            href="{{ route('notifications.show', $notification->id) }}"
                            @class([
                                'flex items-start gap-3 rounded-xl border px-4 py-3.5 transition',
                                'bg-pitch-900 border-emerald-500/30 hover:border-emerald-500/50' => $unread,
                                'bg-pitch-900/50 border-pitch-800 hover:border-pitch-700' => ! $unread,
                            ])
                        >
                            <span @class([
                                'flex items-center justify-center w-9 h-9 rounded-lg shrink-0',
                                'bg-emerald-500/15 text-emerald-400' => $unread,
                                'bg-pitch-800 text-pitch-500' => ! $unread,
                            ])>
                                <x-dynamic-component :component="$data['icon'] ?? 'heroicon-o-bell'" class="w-5 h-5" />
                            </span>

                            <div class="min-w-0 grow">
                                <p @class(['text-sm font-bold truncate', 'text-white' => $unread, 'text-pitch-300' => ! $unread])>
                                    {{ $data['title'] ?? __('Notificação') }}
                                </p>
                                @isset($data['body'])
                                    <p class="text-sm text-pitch-400">{{ $data['body'] }}</p>
                                @endisset
                                <p class="mt-1 text-xs text-pitch-500">{{ $notification->created_at->diffForHumans() }}</p>
                            </div>

                            @if ($unread)
                                <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0 mt-2"></span>
                            @endif
                        </a>
                    @endforeach
                </div>

                {{ $notifications->links() }}
            @endif
        </div>
    </div>
</x-app-layout>
