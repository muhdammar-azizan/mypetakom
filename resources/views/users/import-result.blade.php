<x-app-layout title="Import Students">
    <x-slot name="header">Import Finished</x-slot>
    <x-slot name="actions"><a href="{{ route('users.index') }}" class="text-sm font-semibold text-brand-600">Go to user accounts &rarr;</a></x-slot>

    <div class="flex flex-col gap-5 max-w-4xl">

        @if (count($created))
            <div role="alert" class="px-4 py-3 rounded-xl border bg-gold-50 border-gold-300 text-gold-700 text-sm font-semibold">
                These passwords are shown only once. Download them now: they cannot be seen again after you leave this page.
            </div>

            <x-card class="p-0 overflow-hidden"
                    x-data="{
                        csv: @js($credentials),
                        save() {
                            const blob = new Blob([this.csv], { type: 'text/csv;charset=utf-8' });
                            const link = document.createElement('a');
                            link.href = URL.createObjectURL(blob);
                            link.download = 'student-credentials.csv';
                            link.click();
                            URL.revokeObjectURL(link.href);
                        }
                    }">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-slate-200">
                    <div>
                        <div class="font-head font-bold text-slate-900">{{ count($created) }} {{ \Illuminate\Support\Str::plural('account', count($created)) }} created</div>
                        <p class="text-xs text-slate-500">From {{ $filename }}. Students should change their password in Settings after the first login.</p>
                    </div>
                    <x-primary-button type="button" @click="save()">Download credentials (CSV)</x-primary-button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-4 py-3">Student</th><th class="px-4 py-3">Student ID</th><th class="px-4 py-3">Password</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($created as $c)
                                <tr>
                                    <td class="px-4 py-3"><div class="font-semibold text-slate-800">{{ $c['user']->name }}</div><div class="text-xs text-slate-500">{{ $c['user']->email }}</div></td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $c['user']->student_id }}</td>
                                    <td class="px-4 py-3 font-mono text-slate-900">{{ $c['password'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @else
            <x-card><p class="text-sm text-slate-600">No accounts were created. Every row had a problem.</p></x-card>
        @endif

        @if (count($skipped))
            <x-card title="Skipped rows" subtitle="These rows were not imported. Fix them and upload the file again.">
                <ul class="flex flex-col divide-y divide-slate-100 text-sm">
                    @foreach ($skipped as $row)
                        <li class="py-2.5">
                            <span class="font-mono text-xs text-slate-500">Row {{ $row['line'] }}</span>
                            <span class="font-semibold text-slate-800">{{ $row['name'] !== '' ? $row['name'] : '(no name)' }}</span>
                            <span class="block text-xs text-danger-700">{{ implode(' ', $row['errors']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <div><a href="{{ route('users.import') }}" class="text-sm font-semibold text-brand-600">Import another file</a></div>
    </div>
</x-app-layout>
