@php
    $isStudent = auth()->user()->isStudent();
    $statuses = $isStudent ? ['pending' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected']
                           : ['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected'];
@endphp

<x-app-layout title="Merit Claims">
    <x-slot name="header">Merit Claims</x-slot>
    @if ($isStudent)
        <x-slot name="actions"><a href="{{ route('merit-claims.create') }}"><x-primary-button type="button">+ New claim</x-primary-button></a></x-slot>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-{{ count($statuses) }} gap-3 sm:gap-5 mb-5">
        @foreach ($statuses as $key => $label)
            <a href="{{ route('merit-claims.index', ['status' => $key]) }}" class="bg-white border border-slate-200 rounded-2xl p-4 hover:shadow-md transition">
                <div class="text-xs text-slate-500">{{ $label }}</div>
                <div class="font-head text-2xl font-bold text-slate-900">{{ $counts[$key] ?? 0 }}</div>
            </a>
        @endforeach
    </div>

    <x-card class="p-0 overflow-hidden">
        <form method="GET" class="flex flex-wrap gap-3 p-4 border-b border-slate-200">
            <select name="status" aria-label="Filter by status" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
                <option value="">All statuses</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <x-primary-button>Filter</x-primary-button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Event</th>
                        @unless ($isStudent) <th class="px-4 py-3">Student</th> @endunless
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Points</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($claims as $c)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $c->event->title }}</td>
                            @unless ($isStudent)
                                <td class="px-4 py-3"><div>{{ $c->user->name }}</div><div class="font-mono text-xs text-slate-500">{{ $c->user->student_id }}</div></td>
                            @endunless
                            <td class="px-4 py-3 text-slate-600">{{ $c->role_label }}</td>
                            <td class="px-4 py-3 font-mono">{{ $c->points }}</td>
                            <td class="px-4 py-3"><x-badge :color="$c->status_color">{{ $c->status_label }}</x-badge></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('merit-claims.show', $c) }}" class="font-semibold text-brand-600">{{ ! $isStudent && $c->status === 'submitted' ? 'Review' : 'View' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No merit claims found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($claims->hasPages())
            <div class="p-4 border-t border-slate-200">{{ $claims->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
