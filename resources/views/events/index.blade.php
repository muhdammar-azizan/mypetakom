@php $isAdvisor = auth()->user()->isAdvisor(); @endphp

<x-app-layout :title="$isAdvisor ? 'My Events' : 'Events'">
    <x-slot name="header">{{ $isAdvisor ? 'My Events' : 'Events' }}</x-slot>
    <x-slot name="actions">
        <div class="flex items-center gap-3">
            <a href="{{ route('events.export', request()->query()) }}"><x-secondary-button type="button">Export CSV</x-secondary-button></a>
            @if ($isAdvisor)
                <a href="{{ route('events.create') }}"><x-primary-button type="button">+ New event</x-primary-button></a>
            @endif
        </div>
    </x-slot>

    <x-card class="p-0 overflow-hidden">
        <form method="GET" class="flex flex-wrap gap-3 p-4 border-b border-slate-200">
            <x-text-input name="q" :value="request('q')" placeholder="Search title or venue" aria-label="Search title or venue" class="flex-1 min-w-[200px]" />
            <select name="level" aria-label="Filter by level" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
                <option value="">All levels</option>
                @foreach (\App\Models\Event::LEVELS as $value => $label)
                    <option value="{{ $value }}" @selected(request('level') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Filter by status" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
                <option value="">All statuses</option>
                @foreach (\App\Models\Event::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-date-range />
            <x-primary-button>Filter</x-primary-button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Level</th>
                        @unless ($isAdvisor) <th class="px-4 py-3">Advisor</th> @endunless
                        <th class="px-4 py-3">Committee</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($events as $e)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $e->title }}</div>
                                <div class="text-xs text-slate-500">{{ $e->venue }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $e->starts_at->format('d M Y') }}</td>
                            <td class="px-4 py-3"><x-badge color="brand">{{ $e->level_label }}</x-badge></td>
                            @unless ($isAdvisor) <td class="px-4 py-3 text-slate-600">{{ $e->advisor->name }}</td> @endunless
                            <td class="px-4 py-3 text-slate-600">{{ $e->committees_count }}</td>
                            <td class="px-4 py-3"><x-badge :color="$e->status_color">{{ $e->status_label }}</x-badge></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('events.show', $e) }}" class="font-semibold text-brand-600">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No events found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($events->hasPages())
            <div class="p-4 border-t border-slate-200">{{ $events->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
