@php
    $total = array_sum($statusCounts);
    $segments = [
        ['pending', 'Pending', 'bg-gold-500', 'text-gold-700'],
        ['approved', 'Approved', 'bg-ok-600', 'text-ok-600'],
        ['rejected', 'Rejected', 'bg-danger-600', 'text-danger-600'],
    ];
@endphp

<x-app-layout title="Dashboard">
    <x-slot name="header">Dashboard</x-slot>

    {{-- Headline tiles --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-5 mb-5">
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

        {{-- Applications per month --}}
        <x-card title="Membership applications per month" subtitle="New applications over the last 6 months.">
            <div class="h-56"><canvas data-chart="bar" data-label="Applications"
                data-labels='@json($monthLabels)' data-values='@json($monthValues)'
                role="img" aria-label="Bar chart of membership applications per month"></canvas></div>
            <details class="mt-3 text-xs text-slate-500">
                <summary class="cursor-pointer font-semibold">View as table</summary>
                <table class="mt-2 w-full">
                    @foreach ($monthLabels as $i => $label)
                        <tr class="border-t border-slate-100"><td class="py-1">{{ $label }}</td><td class="py-1 text-right font-mono">{{ $monthValues[$i] }}</td></tr>
                    @endforeach
                </table>
            </details>
        </x-card>

        {{-- Approved members per programme --}}
        <x-card title="Approved members per programme" subtitle="Top programmes by approved membership.">
            @if (count($programmeLabels))
                <div class="h-56"><canvas data-chart="hbar" data-label="Approved members"
                    data-labels='@json($programmeLabels)' data-values='@json($programmeValues)'
                    role="img" aria-label="Bar chart of approved members per programme"></canvas></div>
                <details class="mt-3 text-xs text-slate-500">
                    <summary class="cursor-pointer font-semibold">View as table</summary>
                    <table class="mt-2 w-full">
                        @foreach ($programmeLabels as $i => $label)
                            <tr class="border-t border-slate-100"><td class="py-1">{{ $label }}</td><td class="py-1 text-right font-mono">{{ $programmeValues[$i] }}</td></tr>
                        @endforeach
                    </table>
                </details>
            @else
                <p class="text-sm text-slate-500 py-10 text-center">No approved members yet.</p>
            @endif
        </x-card>

        {{-- Status split --}}
        <x-card title="Application status" subtitle="Share of all membership applications.">
            @if ($total > 0)
                <div class="flex h-4 gap-0.5 rounded-full overflow-hidden" role="img"
                     aria-label="Pending {{ $statusCounts['pending'] }}, approved {{ $statusCounts['approved'] }}, rejected {{ $statusCounts['rejected'] }}">
                    @foreach ($segments as [$key, $label, $bg])
                        @if ($statusCounts[$key] > 0)
                            <div class="{{ $bg }}" style="width: {{ $statusCounts[$key] / $total * 100 }}%" title="{{ $label }}: {{ $statusCounts[$key] }}"></div>
                        @endif
                    @endforeach
                </div>
                <ul class="mt-4 grid grid-cols-3 gap-3 text-sm">
                    @foreach ($segments as [$key, $label, $bg, $text])
                        <li>
                            <span class="inline-block w-2.5 h-2.5 rounded-sm {{ $bg }} mr-1"></span>
                            <span class="text-slate-600">{{ $label }}</span>
                            <div class="font-head text-xl font-bold {{ $text }}">{{ $statusCounts[$key] }}
                                <span class="text-xs font-normal text-slate-500">({{ round($statusCounts[$key] / $total * 100) }}%)</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-slate-500 py-6 text-center">No applications yet.</p>
            @endif
        </x-card>

        {{-- Merit applications queue --}}
        <x-card title="Merit applications waiting" subtitle="Events that applied for merit.">
            @forelse ($pendingMerit as $a)
                <a href="{{ route('merit-applications.show', $a) }}" class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 first:border-t-0 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-800 truncate">{{ $a->event->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $a->event->level_label }} &middot; {{ $a->event->advisor->name }}</span>
                    </span>
                    <span class="text-[13px] font-semibold text-brand-600 shrink-0">Review</span>
                </a>
            @empty
                <p class="text-sm text-slate-500 py-6 text-center">No merit applications waiting.</p>
            @endforelse
        </x-card>

        {{-- Pending queue --}}
        <x-card title="Waiting for review" subtitle="Latest pending membership applications.">
            @forelse ($pending as $m)
                <a href="{{ route('members.show', $m) }}" class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 first:border-t-0 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-800 truncate">{{ $m->user->name }}</span>
                        <span class="block text-xs text-slate-500 font-mono">{{ $m->user->student_id }} &middot; {{ $m->created_at->diffForHumans() }}</span>
                    </span>
                    <span class="text-[13px] font-semibold text-brand-600 shrink-0">Review</span>
                </a>
            @empty
                <p class="text-sm text-slate-500 py-6 text-center">All caught up. No pending applications.</p>
            @endforelse
        </x-card>
    </div>
</x-app-layout>
