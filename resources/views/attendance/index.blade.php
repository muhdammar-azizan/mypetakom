<x-app-layout title="Attendance">
    <x-slot name="header">Attendance</x-slot>
    <x-slot name="actions"><a href="{{ route('attendance.create') }}"><x-primary-button type="button">+ New slot</x-primary-button></a></x-slot>

    <x-card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Slot</th>
                        <th class="px-4 py-3">Window</th>
                        <th class="px-4 py-3">Radius</th>
                        <th class="px-4 py-3">Check-ins</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($slots as $s)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $s->title }}</div>
                                <div class="text-xs text-slate-500">{{ $s->event->title }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $s->opens_at->format('d M, h:i A') }} - {{ $s->closes_at->format('h:i A') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $s->radius_m }} m</td>
                            <td class="px-4 py-3 font-mono">{{ $s->attendances_count }}</td>
                            <td class="px-4 py-3"><x-badge :color="$s->window_color">{{ ucfirst($s->window) }}</x-badge></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('attendance.show', $s) }}" class="font-semibold text-brand-600">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No attendance slots yet. Create one to get a check-in QR.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($slots->hasPages())
            <div class="p-4 border-t border-slate-200">{{ $slots->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
