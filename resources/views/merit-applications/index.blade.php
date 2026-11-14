@php
    $isAdvisor = auth()->user()->isAdvisor();
    $tiles = [['pending', 'Pending', 'text-gold-700'], ['approved', 'Approved', 'text-ok-600'], ['rejected', 'Rejected', 'text-danger-600']];
@endphp

<x-app-layout title="Merit Applications">
    <x-slot name="header">Merit Applications</x-slot>

    <div class="grid grid-cols-3 gap-3 sm:gap-5 mb-5">
        @foreach ($tiles as [$key, $label, $color])
            <a href="{{ route('merit-applications.index', ['status' => $key]) }}" class="bg-white border border-slate-200 rounded-2xl p-4 hover:shadow-md transition">
                <div class="text-xs text-slate-500">{{ $label }}</div>
                <div class="font-head text-2xl font-bold {{ $color }}">{{ $counts[$key] ?? 0 }}</div>
            </a>
        @endforeach
    </div>

    <x-card class="p-0 overflow-hidden">
        <form method="GET" class="flex flex-wrap gap-3 p-4 border-b border-slate-200">
            <select name="status" aria-label="Filter by status" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
                <option value="">All statuses</option>
                @foreach (['pending', 'approved', 'rejected'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <x-primary-button>Filter</x-primary-button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Level</th>
                        @unless ($isAdvisor) <th class="px-4 py-3">Advisor</th> @endunless
                        <th class="px-4 py-3">Applied</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($applications as $a)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $a->event->title }}</td>
                            <td class="px-4 py-3"><x-badge color="brand">{{ $a->event->level_label }}</x-badge></td>
                            @unless ($isAdvisor) <td class="px-4 py-3 text-slate-600">{{ $a->event->advisor->name }}</td> @endunless
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $a->updated_at->format('d M Y') }}</td>
                            <td class="px-4 py-3"><x-badge :color="$a->status_color">{{ ucfirst($a->status) }}</x-badge></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('merit-applications.show', $a) }}" class="font-semibold text-brand-600">{{ $isAdvisor ? 'View' : 'Review' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No merit applications found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($applications->hasPages())
            <div class="p-4 border-t border-slate-200">{{ $applications->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
