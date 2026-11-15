@php
    $viewer = auth()->user();
    $period = trim((request('year') ?: 'All time').(request('semester') ? ' · Semester '.request('semester') : ''));
@endphp

<x-app-layout title="Leaderboard">
    <x-slot name="header">Leaderboard</x-slot>

    {{-- Your position (students) --}}
    @if ($viewer->isStudent())
        <div class="mb-5 rounded-2xl bg-brand-vibrant text-white px-5 py-4 flex flex-wrap items-center justify-between gap-3">
            @if ($mine)
                <div>
                    <div class="text-xs uppercase tracking-wide text-white/70">Your rank &middot; {{ $period }}</div>
                    <div class="font-head text-3xl font-bold">#{{ $mine['rank'] }} <span class="text-base font-semibold text-white/80">of {{ $total }}</span></div>
                </div>
                <div class="text-right">
                    <div class="font-head text-2xl font-bold">{{ $mine['points'] }}</div>
                    <div class="text-xs text-white/70">points</div>
                </div>
            @else
                <p class="text-sm">You have no merit for {{ strtolower($period) }} yet, so you are not ranked. Join an event to start earning.</p>
            @endif
        </div>
    @endif

    <x-card class="p-0 overflow-hidden">
        <form method="GET" class="flex flex-wrap items-center gap-3 p-4 border-b border-slate-200">
            <select name="year" aria-label="Academic year" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
                <option value="">All time</option>
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected(request('year') === $y)>{{ $y }}</option>
                @endforeach
            </select>
            <select name="semester" aria-label="Semester" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
                <option value="">All semesters</option>
                @foreach ($semesters as $s)
                    <option value="{{ $s }}" @selected((string) request('semester') === (string) $s)>Semester {{ $s }}</option>
                @endforeach
            </select>
            <x-primary-button>Show</x-primary-button>
            @if (request()->hasAny(['year', 'semester']))
                <a href="{{ route('leaderboard.index') }}" class="text-sm font-semibold text-brand-600">Clear</a>
            @endif
            <span class="ml-auto text-xs text-slate-500">{{ $total }} {{ \Illuminate\Support\Str::plural('student', $total) }} ranked</span>
        </form>

        @error('year') <p class="px-4 pt-3 text-sm text-danger-700">{{ $message }}</p> @enderror
        @error('semester') <p class="px-4 pt-3 text-sm text-danger-700">{{ $message }}</p> @enderror

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">Students ranked by merit points, {{ $period }}</caption>
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 w-16">Rank</th>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3 hidden sm:table-cell">Events</th>
                        <th class="px-4 py-3 w-2/5">Points</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($top as $row)
                        <tr class="{{ $row['mine'] ? 'bg-brand-50' : '' }}">
                            <td class="px-4 py-3">
                                @if ($row['rank'] === 1)
                                    <x-badge color="gold">1</x-badge>
                                @elseif ($row['rank'] <= 3)
                                    <x-badge color="brand">{{ $row['rank'] }}</x-badge>
                                @else
                                    <span class="font-mono text-slate-600 pl-1">{{ $row['rank'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-9 h-9 rounded-full flex items-center justify-center font-head font-bold text-[12px] shrink-0
                                        {{ $row['name'] === 'Anonymous student' ? 'bg-slate-100 text-slate-500' : 'bg-brand-100 text-brand-700' }}">{{ $row['initials'] }}</span>
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-slate-800 truncate">
                                            {{ $row['name'] }}
                                            @if ($row['mine'])<span class="text-xs font-semibold text-brand-600">(you)</span>@endif
                                        </span>
                                        @if ($row['programme'])<span class="block text-xs text-slate-500 truncate">{{ $row['programme'] }}</span>@endif
                                        @if ($row['hidden'] && ($row['mine'] || $viewer->isCoordinator()))
                                            <span class="block text-xs text-slate-500">Hidden from other students</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden sm:table-cell font-mono text-slate-600">{{ $row['events'] }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono font-semibold text-slate-900 w-12 text-right shrink-0">{{ $row['points'] }}</span>
                                    <span class="hidden sm:block flex-1 h-2 rounded-full bg-slate-100 overflow-hidden" aria-hidden="true">
                                        <span class="block h-full rounded-full bg-brand-600" style="width: {{ $maxPoints ? max(2, round($row['points'] / $maxPoints * 100)) : 0 }}%"></span>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">No merit has been recorded for this period yet.</td></tr>
                    @endforelse

                    {{-- A student ranked below the table still sees their own row --}}
                    @if ($mine && ! $mineInTop)
                        <tr><td colspan="4" class="px-4 py-1 text-center text-slate-400" aria-hidden="true">&middot; &middot; &middot;</td></tr>
                        <tr class="bg-brand-50">
                            <td class="px-4 py-3"><span class="font-mono text-slate-600 pl-1">{{ $mine['rank'] }}</span></td>
                            <td class="px-4 py-3"><span class="font-semibold text-slate-800">{{ $mine['name'] }}</span> <span class="text-xs font-semibold text-brand-600">(you)</span></td>
                            <td class="px-4 py-3 hidden sm:table-cell font-mono text-slate-600">{{ $mine['events'] }}</td>
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900">{{ $mine['points'] }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <p class="px-4 py-3 border-t border-slate-100 text-xs text-slate-500">
            Top {{ $top_limit }} students are shown.
            @if ($viewer->isStudent())
                You can hide your name from other students in <a href="{{ route('settings.edit') }}" class="font-semibold text-brand-600">Settings</a>; you keep your rank.
            @elseif ($viewer->isCoordinator())
                Students who hid their name appear as "Anonymous student" to everyone except you and themselves.
            @else
                Students who hid their name appear as "Anonymous student".
            @endif
        </p>
    </x-card>
</x-app-layout>
