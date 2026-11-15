@php
    $isStudent = auth()->user()->isStudent();
    $chip = [
        'green' => 'bg-ok-50 text-ok-600',
        'gold' => 'bg-gold-50 text-gold-700',
        'red' => 'bg-danger-50 text-danger-700 line-through',
        'gray' => 'bg-slate-100 text-slate-600',
    ];
    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
@endphp

<x-app-layout title="Calendar">
    <x-slot name="header">Calendar</x-slot>
    <x-slot name="actions">
        <a href="{{ route('calendar.index') }}"><x-secondary-button type="button">Today</x-secondary-button></a>
    </x-slot>

    <x-card class="p-0 overflow-hidden">
        {{-- Month switcher --}}
        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-200">
            <a href="{{ route('calendar.index', ['month' => $prev]) }}" aria-label="Previous month"
               class="w-11 h-11 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                <x-icon name="chevron-right" :size="16" class="rotate-180" />
            </a>
            <h2 class="font-head text-lg font-bold text-slate-900">{{ $month->format('F Y') }}</h2>
            <a href="{{ route('calendar.index', ['month' => $next]) }}" aria-label="Next month"
               class="w-11 h-11 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                <x-icon name="chevron-right" :size="16" />
            </a>
        </div>

        {{-- Month grid (tablet and desktop) --}}
        <div class="hidden sm:block" role="grid" aria-label="Events in {{ $month->format('F Y') }}">
            <div class="grid grid-cols-7 bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500" role="row">
                @foreach ($weekdays as $d)
                    <div class="px-2 py-2 text-center" role="columnheader">{{ $d }}</div>
                @endforeach
            </div>

            @foreach ($weeks as $week)
                <div class="grid grid-cols-7 border-b border-slate-100 last:border-b-0" role="row">
                    @foreach ($week as $day)
                        <div class="min-h-[104px] p-1.5 border-r border-slate-100 last:border-r-0 {{ $day['in_month'] ? 'bg-white' : 'bg-slate-50/60' }}"
                             role="gridcell" x-data="{ all: false }">
                            <div class="flex justify-end mb-1">
                                <span class="w-6 h-6 inline-flex items-center justify-center rounded-full text-xs font-semibold
                                    {{ $day['is_today'] ? 'bg-brand-700 text-white' : ($day['in_month'] ? 'text-slate-700' : 'text-slate-400') }}"
                                      @if ($day['is_today']) aria-label="Today, {{ $day['date']->format('j F') }}" @endif>{{ $day['date']->day }}</span>
                            </div>

                            <div class="flex flex-col gap-1">
                                @foreach ($day['events'] as $i => $e)
                                    <a href="{{ $urls[$e->id] }}"
                                       @if ($i >= 3) x-show="all" style="display:none" @endif
                                       title="{{ $e->title }}, {{ $e->starts_at->format('h:i A') }}, {{ $e->status_label }}"
                                       aria-label="{{ $e->title }}, {{ $e->starts_at->format('j F, h:i A') }}, {{ $e->status_label }}"
                                       class="block truncate rounded px-1.5 py-0.5 text-[11.5px] font-semibold leading-tight hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-brand-600
                                              {{ $chip[$e->status_color] ?? $chip['gray'] }} {{ in_array($e->id, $committeeIds, true) ? 'ring-1 ring-brand-600' : '' }}">
                                        {{ $e->title }}
                                    </a>
                                @endforeach
                                @if (count($day['events']) > 3)
                                    <button type="button" x-show="! all" @click="all = true" class="text-left px-1.5 text-[11.5px] font-semibold text-brand-600 hover:underline">+{{ count($day['events']) - 3 }} more</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- List (phones) --}}
        <div class="sm:hidden divide-y divide-slate-100">
            @forelse ($agenda as $e)
                <a href="{{ $urls[$e->id] }}" class="flex items-start justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-800 {{ $e->status === 'cancelled' ? 'line-through' : '' }}">{{ $e->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $e->starts_at->format('D, j M, h:i A') }} &middot; {{ $e->venue }}</span>
                        @if (in_array($e->id, $committeeIds, true))
                            <span class="block text-xs font-semibold text-brand-600 mt-0.5">You are on the committee</span>
                        @endif
                    </span>
                    <x-badge :color="$e->status_color">{{ $e->status_label }}</x-badge>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-slate-500">No events this month.</p>
            @endforelse
        </div>

        @if ($agenda->isEmpty())
            <p class="hidden sm:block px-4 py-4 text-center text-sm text-slate-500 border-t border-slate-100">No events this month.</p>
        @endif

        {{-- Legend --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 border-t border-slate-200 text-xs text-slate-600">
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-ok-50 border border-ok-500"></span> Active</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-gold-50 border border-gold-300"></span> Postponed</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-danger-50 border border-danger-500"></span> Cancelled</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-slate-100 border border-slate-300"></span> Completed</span>
            @if ($isStudent)
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-white ring-1 ring-brand-600"></span> You are on the committee</span>
            @endif
        </div>
    </x-card>
</x-app-layout>
