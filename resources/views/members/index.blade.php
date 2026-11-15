@php
    $tiles = [
        ['pending', 'Pending', 'text-gold-700'],
        ['approved', 'Approved', 'text-ok-600'],
        ['rejected', 'Rejected', 'text-danger-600'],
    ];
@endphp

<x-app-layout title="Members">
    <x-slot name="header">Members</x-slot>
    <x-slot name="actions"><a href="{{ route('members.export', request()->query()) }}"><x-secondary-button type="button">Export CSV</x-secondary-button></a></x-slot>

    <div class="grid grid-cols-3 gap-3 sm:gap-5 mb-5">
        @foreach ($tiles as [$key, $label, $color])
            <a href="{{ route('members.index', ['status' => $key]) }}" class="bg-white border border-slate-200 rounded-2xl p-4 hover:shadow-md transition">
                <div class="text-xs text-slate-500">{{ $label }}</div>
                <div class="font-head text-2xl font-bold {{ $color }}">{{ $counts[$key] ?? 0 }}</div>
            </a>
        @endforeach
    </div>

    <x-card class="p-0 overflow-hidden">
        <form method="GET" class="flex flex-wrap gap-3 p-4 border-b border-slate-200">
            <x-text-input name="q" :value="request('q')" placeholder="Search name, student ID or programme" aria-label="Search name, student ID or programme" class="flex-1 min-w-[200px]" />
            <select name="status" aria-label="Filter by status" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
                <option value="">All statuses</option>
                @foreach (['pending', 'approved', 'rejected'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <x-date-range />
            <x-primary-button>Filter</x-primary-button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3">Programme</th>
                        <th class="px-4 py-3">Applied</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($memberships as $m)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $m->user->name }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $m->user->student_id }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $m->user->programme ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $m->created_at->format('d M Y') }}</td>
                            <td class="px-4 py-3"><x-badge :color="$m->status_color">{{ ucfirst($m->status) }}</x-badge></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('members.show', $m) }}" class="font-semibold text-brand-600">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No membership applications found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($memberships->hasPages())
            <div class="p-4 border-t border-slate-200">{{ $memberships->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
