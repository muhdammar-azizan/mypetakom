<x-app-layout title="Reports">
    <x-slot name="header">Reports</x-slot>

    <div class="flex flex-col gap-5 max-w-4xl">
        @foreach ($sections as $s)
            <x-card>
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-600 mb-1">{{ $s['module'] }}</p>
                <h2 class="font-head text-base font-bold text-slate-900">{{ $s['title'] }}</h2>
                <p class="text-xs text-slate-500 mt-1 mb-3">{{ $s['description'] }}</p>

                <pre class="text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg p-3 overflow-x-auto mb-4 whitespace-pre-wrap">{{ $s['sql'] }}</pre>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                @foreach ($s['columns'] as $label)
                                    <th class="px-4 py-2.5">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($s['rows'] as $row)
                                <tr>
                                    @foreach ($s['columns'] as $key => $label)
                                        <td class="px-4 py-2.5">{{ $row->$key ?? '-' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($s['columns']) }}" class="px-4 py-6 text-center text-slate-500">No data yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach
    </div>
</x-app-layout>
