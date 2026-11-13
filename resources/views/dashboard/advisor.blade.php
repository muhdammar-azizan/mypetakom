@php
    $totalApps = array_sum($applicationCounts);
    $segments = [
        ['pending', 'Pending', 'bg-gold-500', 'text-gold-700'],
        ['approved', 'Approved', 'bg-ok-600', 'text-ok-600'],
        ['rejected', 'Rejected', 'bg-danger-600', 'text-danger-600'],
    ];
@endphp

<x-app-layout title="Dashboard">
    <x-slot name="header">Dashboard</x-slot>
    <x-slot name="actions"><a href="{{ route('events.create') }}"><x-primary-button type="button">+ New event</x-primary-button></a></x-slot>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5 mb-5">
        @foreach ($tiles as [$label, $value, $icon])
            <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5">
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <x-icon :name="$icon" :size="15" class="text-brand-600" /> {{ $label }}
                </div>
                <div class="font-head text-3xl font-bold text-slate-900 mt-1">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-2">

        <x-card title="Events by level" subtitle="How many of your events are at each level.">
            <div class="h-56"><canvas data-chart="bar" data-label="Events"
                data-labels='@json($levelLabels)' data-values='@json($levelValues)'
                role="img" aria-label="Bar chart of your events by level"></canvas></div>
            <details class="mt-3 text-xs text-slate-500">
                <summary class="cursor-pointer font-semibold">View as table</summary>
                <table class="mt-2 w-full">
                    @foreach ($levelLabels as $i => $label)
                        <tr class="border-t border-slate-100"><td class="py-1">{{ $label }}</td><td class="py-1 text-right font-mono">{{ $levelValues[$i] }}</td></tr>
                    @endforeach
                </table>
            </details>
        </x-card>

        <x-card title="Committee size per event" subtitle="Your events with the most committee members.">
            @if (count($committeeLabels))
                <div class="h-56"><canvas data-chart="hbar" data-label="Committee members"
                    data-labels='@json($committeeLabels)' data-values='@json($committeeValues)'
                    role="img" aria-label="Bar chart of committee size per event"></canvas></div>
                <details class="mt-3 text-xs text-slate-500">
                    <summary class="cursor-pointer font-semibold">View as table</summary>
                    <table class="mt-2 w-full">
                        @foreach ($committeeLabels as $i => $label)
                            <tr class="border-t border-slate-100"><td class="py-1">{{ $label }}</td><td class="py-1 text-right font-mono">{{ $committeeValues[$i] }}</td></tr>
                        @endforeach
                    </table>
                </details>
            @else
                <p class="text-sm text-slate-500 py-10 text-center">No events yet.</p>
            @endif
        </x-card>

        <x-card title="Upcoming events" subtitle="Your next active or postponed events.">
            @forelse ($upcoming as $e)
                <a href="{{ route('events.show', $e) }}" class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 first:border-t-0 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-800 truncate">{{ $e->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $e->starts_at->format('d M Y, h:i A') }} &middot; {{ $e->venue }}</span>
                    </span>
                    <x-badge :color="$e->status_color">{{ $e->status_label }}</x-badge>
                </a>
            @empty
                <p class="text-sm text-slate-500 py-6 text-center">No upcoming events.</p>
            @endforelse
        </x-card>

        <x-card title="Merit applications" subtitle="Status of your merit applications.">
            @if ($totalApps > 0)
                <div class="flex h-4 gap-0.5 rounded-full overflow-hidden" role="img"
                     aria-label="Pending {{ $applicationCounts['pending'] }}, approved {{ $applicationCounts['approved'] }}, rejected {{ $applicationCounts['rejected'] }}">
                    @foreach ($segments as [$key, $label, $bg])
                        @if ($applicationCounts[$key] > 0)
                            <div class="{{ $bg }}" style="width: {{ $applicationCounts[$key] / $totalApps * 100 }}%" title="{{ $label }}: {{ $applicationCounts[$key] }}"></div>
                        @endif
                    @endforeach
                </div>
                <ul class="mt-4 grid grid-cols-3 gap-3 text-sm">
                    @foreach ($segments as [$key, $label, $bg, $text])
                        <li>
                            <span class="inline-block w-2.5 h-2.5 rounded-sm {{ $bg }} mr-1"></span><span class="text-slate-600">{{ $label }}</span>
                            <div class="font-head text-xl font-bold {{ $text }}">{{ $applicationCounts[$key] }}</div>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('merit-applications.index') }}" class="mt-4 inline-block text-[13px] font-semibold text-brand-600">View all applications</a>
            @else
                <p class="text-sm text-slate-500 py-6 text-center">No merit applications yet.</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
