@php $roleColors = ['student' => 'brand', 'advisor' => 'gold', 'coordinator' => 'solid']; @endphp

<x-app-layout title="User Accounts">
    <x-slot name="header">User Accounts</x-slot>
    <x-slot name="actions"><a href="{{ route('users.create') }}"><x-primary-button type="button">+ New account</x-primary-button></a></x-slot>

    <x-card class="p-0 overflow-hidden">
        <form method="GET" class="flex flex-wrap gap-3 p-4 border-b border-slate-200">
            <x-text-input name="q" :value="request('q')" placeholder="Search name, email or ID" class="flex-1 min-w-[200px]" />
            <select name="role" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
                <option value="">All roles</option>
                @foreach (['student' => 'Student', 'advisor' => 'Event Advisor', 'coordinator' => 'Coordinator'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-primary-button>Filter</x-primary-button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $u)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $u->name }}</div>
                                <div class="text-xs text-slate-500">{{ $u->email }}</div>
                            </td>
                            <td class="px-4 py-3"><x-badge :color="$roleColors[$u->role]">{{ $u->role_label }}</x-badge></td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $u->id_value }}</td>
                            <td class="px-4 py-3">
                                @if ($u->isDeactivated())
                                    <x-badge color="gray">Deactivated</x-badge>
                                @else
                                    <x-badge color="green">Active</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('users.edit', $u) }}" class="font-semibold text-brand-600">Edit</a>
                                @unless ($u->is(auth()->user()))
                                    <form method="POST" action="{{ route('users.toggle', $u) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="ml-3 font-semibold text-slate-600 hover:underline">{{ $u->isDeactivated() ? 'Activate' : 'Deactivate' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline" onsubmit="return confirm('Delete {{ addslashes($u->name) }}? This cannot be undone.')">
                                        @csrf @method('DELETE')
                                        <button class="ml-3 font-semibold text-danger-600 hover:underline">Delete</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="p-4 border-t border-slate-200">{{ $users->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
