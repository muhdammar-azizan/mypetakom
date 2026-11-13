<x-app-layout title="Attendance">
    <x-slot name="header">Attendance</x-slot>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5 mb-5">
        @foreach ($tiles as [$label, $value, $icon])
            <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5">
                <div class="flex items-center gap-2 text-xs text-slate-500"><x-icon :name="$icon" :size="15" class="text-brand-600" /> {{ $label }}</div>
                <div class="font-head text-3xl font-bold text-slate-900 mt-1">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-2 mb-5">
        <x-card title="Check-ins per day" subtitle="Last 14 days.">
            <div class="h-56"><canvas data-chart="bar" data-label="Check-ins"
                data-labels='@json($dayLabels)' data-values='@json($dayValues)'
                role="img" aria-label="Bar chart of check-ins per day"></canvas></div>
            <details class="mt-3 text-xs text-slate-500">
                <summary class="cursor-pointer font-semibold">View as table</summary>
                <table class="mt-2 w-full">
                    @foreach ($dayLabels as $i => $label)
                        <tr class="border-t border-slate-100"><td class="py-1">{{ $label }}</td><td class="py-1 text-right font-mono">{{ $dayValues[$i] }}</td></tr>
                    @endforeach
                </table>
            </details>
        </x-card>

        <x-card title="Check-ins per event" subtitle="Events with the highest attendance.">
            @if (count($eventLabels))
                <div class="h-56"><canvas data-chart="hbar" data-label="Check-ins"
                    data-labels='@json($eventLabels)' data-values='@json($eventValues)'
                    role="img" aria-label="Bar chart of check-ins per event"></canvas></div>
                <details class="mt-3 text-xs text-slate-500">
                    <summary class="cursor-pointer font-semibold">View as table</summary>
                    <table class="mt-2 w-full">
                        @foreach ($eventLabels as $i => $label)
                            <tr class="border-t border-slate-100"><td class="py-1">{{ $label }}</td><td class="py-1 text-right font-mono">{{ $eventValues[$i] }}</td></tr>
                        @endforeach
                    </table>
                </details>
            @else
                <p class="text-sm text-slate-500 py-10 text-center">No check-ins yet.</p>
            @endif
        </x-card>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_1.5fr]">
        <x-card title="Recent check-ins">
            @forelse ($recent as $a)
                <div class="py-2.5 border-t border-slate-100 first:border-t-0">
                    <div class="text-sm font-semibold text-slate-800">{{ $a->user->name }}</div>
                    <div class="text-xs text-slate-500">{{ $a->slot->event->title }} &middot; {{ $a->checked_in_at->diffForHumans() }}</div>
                </div>
            @empty
                <p class="text-sm text-slate-500 py-6 text-center">No check-ins yet.</p>
            @endforelse
        </x-card>

        <x-card class="p-0 overflow-hidden">
            <div class="px-6 pt-6 pb-3"><h2 class="font-head text-base font-bold text-slate-900">All attendance slots</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-3">Slot</th><th class="px-4 py-3">Advisor</th><th class="px-4 py-3">Check-ins</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($slots as $s)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3"><div class="font-semibold text-slate-800">{{ $s->title }}</div><div class="text-xs text-slate-500">{{ $s->event->title }}</div></td>
                                <td class="px-4 py-3 text-slate-600">{{ $s->event->advisor->name }}</td>
                                <td class="px-4 py-3 font-mono">{{ $s->attendances_count }}</td>
                                <td class="px-4 py-3"><x-badge :color="$s->window_color">{{ ucfirst($s->window) }}</x-badge></td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('attendance.show', $s) }}" class="font-semibold text-brand-600">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No attendance slots yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($slots->hasPages())
                <div class="p-4 border-t border-slate-200">{{ $slots->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
