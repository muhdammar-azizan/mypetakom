@php $sourceLabels = ['committee' => 'Committee', 'attendance' => 'Attendance', 'claim' => 'Approved claim']; @endphp

<x-app-layout title="Merit">
    <x-slot name="header">My Merit</x-slot>
    <x-slot name="actions"><a href="{{ route('merit-claims.create') }}"><x-secondary-button type="button">Claim missing merit</x-secondary-button></a></x-slot>

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-5 mb-5">
        <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5">
            <div class="flex items-center gap-2 text-xs text-slate-500"><x-icon name="award" :size="15" class="text-brand-600" /> Total merit</div>
            <div class="font-head text-3xl font-bold text-slate-900 mt-1">{{ $total }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5">
            <div class="flex items-center gap-2 text-xs text-slate-500"><x-icon name="calendar" :size="15" class="text-brand-600" /> Events with merit</div>
            <div class="font-head text-3xl font-bold text-slate-900 mt-1">{{ $merits->count() }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 col-span-2 lg:col-span-1">
            <div class="flex items-center gap-2 text-xs text-slate-500"><x-icon name="chart" :size="15" class="text-brand-600" /> Semesters</div>
            <div class="font-head text-3xl font-bold text-slate-900 mt-1">{{ $semesters->count() }}</div>
        </div>
    </div>

    <div class="grid gap-5 items-start lg:grid-cols-[1fr_1.4fr]">
        <div class="flex flex-col gap-5">
        <x-card title="Merit per academic year">
            @forelse ($years as $y)
                <div class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 first:border-t-0">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">{{ $y['academic_year'] }}</div>
                        <div class="text-xs text-slate-500">{{ $y['events'] }} {{ Str::plural('event', $y['events']) }} &middot; {{ $y['semesters'] }} {{ Str::plural('semester', $y['semesters']) }}</div>
                    </div>
                    <div class="font-mono text-sm font-semibold text-slate-800">{{ $y['points'] }}</div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No merit yet.</p>
            @endforelse
        </x-card>

        <x-card title="Cumulative merit per semester">
            @forelse ($semesters as $s)
                <div class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 first:border-t-0">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">{{ $s['academic_year'] }} &middot; Sem {{ $s['semester'] }}</div>
                        <div class="text-xs text-slate-500">{{ $s['events'] }} {{ Str::plural('event', $s['events']) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-mono text-sm text-slate-800">+{{ $s['points'] }}</div>
                        <div class="text-xs text-slate-500">Cumulative {{ $s['cumulative'] }}</div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No merit yet. Join events to earn merit.</p>
            @endforelse
        </x-card>
        </div>

        <x-card class="p-0 overflow-hidden">
            <div class="px-6 pt-6 pb-3"><h2 class="font-head text-base font-bold text-slate-900">Merit awarded</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-3">Event</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">From</th><th class="px-4 py-3 text-right">Points</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($merits as $m)
                            <tr>
                                <td class="px-4 py-3"><div class="font-semibold text-slate-800">{{ $m->event->title }}</div><div class="text-xs text-slate-500">{{ $m->event->level_label }} &middot; {{ $m->academic_year }} Sem {{ $m->semester }}</div></td>
                                <td class="px-4 py-3"><x-badge color="brand">{{ $m->role_label }}</x-badge></td>
                                <td class="px-4 py-3 text-slate-600">{{ $sourceLabels[$m->source] ?? $m->source }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ $m->points }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Nothing here yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-app-layout>
