<x-app-layout title="Notifications">
    <x-slot name="header">Notifications</x-slot>
    @if (auth()->user()->unreadNotifications()->exists())
        <x-slot name="actions">
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                <button class="text-sm font-semibold text-brand-600">Mark all as read</button>
            </form>
        </x-slot>
    @endif

    <div class="max-w-3xl">
        <x-card>
            @forelse ($notifications as $n)
                <form method="POST" action="{{ route('notifications.read', $n->id) }}">@csrf
                    <button class="w-full text-left py-3.5 border-t border-slate-100 first:border-t-0 hover:bg-slate-50 -mx-2 px-2 rounded-lg flex gap-3">
                        <span class="mt-2 w-2 h-2 rounded-full shrink-0 {{ $n->read_at ? 'bg-slate-200' : 'bg-brand-600' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm {{ $n->read_at ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $n->data['title'] }}</span>
                            <span class="block text-[13px] text-slate-500">{{ $n->data['body'] }}</span>
                        </span>
                        <span class="text-xs text-slate-400 shrink-0">{{ $n->created_at->diffForHumans() }}</span>
                    </button>
                </form>
            @empty
                <p class="text-sm text-slate-500 py-10 text-center">No notifications yet.</p>
            @endforelse
        </x-card>
        <div class="mt-4">{{ $notifications->links() }}</div>
    </div>
</x-app-layout>
