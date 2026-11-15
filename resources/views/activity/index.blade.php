<x-app-layout title="Activity Log">
    <x-slot name="header">Activity Log</x-slot>

    <x-card class="p-0 overflow-hidden">
        <form method="GET" class="flex flex-wrap gap-3 p-4 border-b border-slate-200">
            <x-text-input name="q" :value="request('q')" placeholder="Search description" aria-label="Search description" class="flex-1 min-w-[200px]" />
            <select name="group" aria-label="Filter by area" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
                <option value="">All areas</option>
                @foreach (\App\Models\ActivityLog::GROUPS as $value => $label)
                    <option value="{{ $value }}" @selected(request('group') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="user" aria-label="Filter by staff member" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
                <option value="">All staff</option>
                @foreach ($staff as $s)
                    <option value="{{ $s->id }}" @selected((string) request('user') === (string) $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" aria-label="From date" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
            <input type="date" name="to" value="{{ request('to') }}" aria-label="To date" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
            <x-primary-button>Filter</x-primary-button>
        </form>

        @error('to') <p class="px-4 pt-3 text-sm text-danger-700">{{ $message }}</p> @enderror

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Who</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-slate-50 align-top">
                            <td class="px-4 py-3 whitespace-nowrap text-slate-600">
                                <div>{{ $log->created_at->format('d M Y') }}</div>
                                <div class="text-xs text-slate-500">{{ $log->created_at->format('h:i A') }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $log->actor_name ?? 'System' }}</div>
                                @if ($log->actor_role)<div class="text-xs text-slate-500 capitalize">{{ $log->actor_role }}</div>@endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs text-slate-500 mb-1">{{ $log->group_label }}</div>
                                <x-badge :color="$log->color">{{ $log->action_label }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $log->description }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">No activity found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="p-4 border-t border-slate-200">{{ $logs->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
