<x-app-layout title="My Events">
    <x-slot name="header">My Events</x-slot>

    <div class="grid gap-4 max-w-4xl">
        @forelse ($committees as $c)
            @php $e = $c->event; @endphp
            <a href="{{ route('events.show', $e) }}" class="block bg-white border border-slate-200 rounded-2xl p-5 hover:shadow-md transition">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-head font-bold text-slate-900">{{ $e->title }}</h2>
                        <p class="text-sm text-slate-500 mt-0.5">{{ $e->starts_at->format('d M Y, h:i A') }} &middot; {{ $e->venue }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-badge :color="$e->status_color">{{ $e->status_label }}</x-badge>
                        <x-badge color="brand">{{ $e->level_label }}</x-badge>
                    </div>
                </div>
                <div class="mt-3 text-sm text-slate-600">
                    My role: <span class="font-semibold text-slate-800">{{ $c->position_label }}</span>{{ $c->position_title ? ' ('.$c->position_title.')' : '' }}
                </div>
                @if (in_array($e->status, ['postponed', 'cancelled']))
                    <p class="mt-2 text-xs font-semibold {{ $e->status === 'cancelled' ? 'text-danger-600' : 'text-gold-700' }}">This event has been {{ $e->status }}.</p>
                @endif
            </a>
        @empty
            <x-card><p class="text-sm text-slate-500 text-center py-6">You are not in any event committee yet.</p></x-card>
        @endforelse
    </div>
</x-app-layout>
