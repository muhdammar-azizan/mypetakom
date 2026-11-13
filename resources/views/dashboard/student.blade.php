@php
    $status = $membership?->status;
    $claimTotal = array_sum($claimCounts);
@endphp

<x-app-layout title="Dashboard">
    <x-slot name="header">Dashboard</x-slot>
    <x-slot name="actions"><a href="{{ route('merit.index') }}" class="text-sm font-semibold text-brand-600">View all merit &rarr;</a></x-slot>

    {{-- Membership banner --}}
    @if ($status !== 'approved')
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 px-4 py-3 rounded-xl border {{ $status === 'rejected' ? 'bg-danger-50 border-danger-500 text-danger-700' : 'bg-gold-50 border-gold-300 text-gold-700' }}">
            <span class="text-sm font-semibold">
                @if (! $membership) You are not a Petakom member yet. Apply to join events and earn merit.
                @elseif ($status === 'pending') Your membership application is waiting for review.
                @else Your membership application was rejected. Please submit it again.
                @endif
            </span>
            <a href="{{ route('membership.index') }}" class="text-sm font-bold underline">{{ $membership ? 'View application' : 'Apply now' }}</a>
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5 mb-5">
        @foreach ($tiles as [$label, $value, $icon])
            <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5">
                <div class="flex items-center gap-2 text-xs text-slate-500"><x-icon :name="$icon" :size="15" class="text-brand-600" /> {{ $label }}</div>
                <div class="font-head text-3xl font-bold text-slate-900 mt-1">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-2">

        <x-card title="Cumulative merit" subtitle="Your total merit at the end of each semester.">
            @if (count($semesterLabels))
                <div class="h-56"><canvas data-chart="line" data-label="Cumulative merit"
                    data-labels='@json($semesterLabels)' data-values='@json($semesterValues)'
                    role="img" aria-label="Line chart of cumulative merit per semester"></canvas></div>
                <details class="mt-3 text-xs text-slate-500">
                    <summary class="cursor-pointer font-semibold">View as table</summary>
                    <table class="mt-2 w-full">
                        @foreach ($semesterLabels as $i => $label)
                            <tr class="border-t border-slate-100"><td class="py-1">{{ $label }}</td><td class="py-1 text-right font-mono">{{ $semesterValues[$i] }}</td></tr>
                        @endforeach
                    </table>
                </details>
            @else
                <p class="text-sm text-slate-500 py-10 text-center">No merit yet. Join an event to start earning.</p>
            @endif
        </x-card>

        <x-card title="Merit by event level" subtitle="Where your merit points come from.">
            @if (array_sum($levelValues) > 0)
                <div class="h-56"><canvas data-chart="hbar" data-label="Points"
                    data-labels='@json($levelLabels)' data-values='@json($levelValues)'
                    role="img" aria-label="Bar chart of merit points by event level"></canvas></div>
                <details class="mt-3 text-xs text-slate-500">
                    <summary class="cursor-pointer font-semibold">View as table</summary>
                    <table class="mt-2 w-full">
                        @foreach ($levelLabels as $i => $label)
                            <tr class="border-t border-slate-100"><td class="py-1">{{ $label }}</td><td class="py-1 text-right font-mono">{{ $levelValues[$i] }}</td></tr>
                        @endforeach
                    </table>
                </details>
            @else
                <p class="text-sm text-slate-500 py-10 text-center">No merit points yet.</p>
            @endif
        </x-card>

        <x-card title="Upcoming committee events" subtitle="Events you are helping to organise.">
            @forelse ($upcoming as $c)
                <a href="{{ route('events.show', $c->event) }}" class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 first:border-t-0 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-800 truncate">{{ $c->event->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $c->event->starts_at->format('d M Y, h:i A') }} &middot; {{ $c->position_label }}</span>
                    </span>
                    <x-badge :color="$c->event->status_color">{{ $c->event->status_label }}</x-badge>
                </a>
            @empty
                <p class="text-sm text-slate-500 py-6 text-center">No upcoming committee events.</p>
            @endforelse
        </x-card>

        <x-card title="Recent merit">
            @forelse ($recentMerits as $m)
                <div class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 first:border-t-0">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-800 truncate">{{ $m->event->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $m->role_label }} &middot; {{ $m->event->level_label }}</span>
                    </span>
                    <span class="font-mono text-sm text-ok-600 font-semibold shrink-0">+{{ $m->points }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500 py-6 text-center">No merit awarded yet.</p>
            @endforelse
        </x-card>

        <x-card title="My merit claims" subtitle="Claims for missing merit." class="lg:col-span-2">
            @if ($claimTotal > 0)
                <ul class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    @foreach (['draft' => ['Draft', 'text-slate-700'], 'submitted' => ['Submitted', 'text-gold-700'], 'approved' => ['Approved', 'text-ok-600'], 'rejected' => ['Rejected', 'text-danger-600']] as $key => [$label, $color])
                        <li><span class="text-slate-600">{{ $label }}</span><div class="font-head text-xl font-bold {{ $color }}">{{ $claimCounts[$key] }}</div></li>
                    @endforeach
                </ul>
                <a href="{{ route('merit-claims.index') }}" class="mt-4 inline-block text-[13px] font-semibold text-brand-600">View my claims</a>
            @else
                <p class="text-sm text-slate-500">Missing merit from an event you joined? <a href="{{ route('merit-claims.create') }}" class="font-semibold text-brand-600">Submit a claim</a>.</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
