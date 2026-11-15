@php
    $rows = $preview['rows'] ?? [];
    $problems = collect($rows)->filter(fn ($r) => $r['errors'])->count();
    $ready = count($rows) - $problems;
@endphp

<x-app-layout title="Import Students">
    <x-slot name="header">Import Students</x-slot>
    <x-slot name="actions"><a href="{{ route('users.index') }}" class="text-sm font-semibold text-brand-600">&larr; Back to accounts</a></x-slot>

    @if (! $preview)
        {{-- Step 1: upload --}}
        <div class="grid gap-5 items-start lg:grid-cols-[1.3fr_1fr] max-w-5xl">
            <x-card title="Upload a CSV file" subtitle="You will see a preview before anything is saved.">
                <form method="POST" action="{{ route('users.import.preview') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                    @csrf
                    <div>
                        <x-input-label for="file" value="CSV file" />
                        <input id="file" name="file" type="file" accept=".csv,text/csv,text/plain" required
                               class="block w-full text-sm text-slate-700 file:mr-3 file:min-h-[44px] file:px-4 file:rounded-lg file:border file:border-slate-300 file:bg-white file:text-[13px] file:font-semibold file:text-slate-700 hover:file:bg-slate-50">
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>
                    <div class="flex justify-end">
                        <x-primary-button>Check file</x-primary-button>
                    </div>
                </form>
            </x-card>

            <x-card title="How it works">
                <ol class="list-decimal pl-5 text-sm text-slate-700 flex flex-col gap-2">
                    <li>The first row must be: <span class="font-mono text-xs">name, email, student_id, programme</span>.</li>
                    <li>Up to <strong>{{ $maxRows }}</strong> students per file. Comma or semicolon both work.</li>
                    <li>Each student gets a <strong>random password</strong>. You will see all passwords once, after the import.</li>
                    <li>Students are created without membership. They apply for membership after they log in.</li>
                </ol>
                <a href="{{ route('users.import.template') }}" class="mt-4 inline-block text-[13px] font-semibold text-brand-600">Download the template (CSV)</a>
            </x-card>
        </div>
    @else
        {{-- Step 2: preview --}}
        <div class="grid grid-cols-3 gap-3 sm:gap-5 mb-5 max-w-3xl">
            <div class="bg-white border border-slate-200 rounded-2xl p-4">
                <div class="text-xs text-slate-500">Rows in file</div>
                <div class="font-head text-3xl font-bold text-slate-900 mt-1">{{ count($rows) }}</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl p-4">
                <div class="text-xs text-slate-500">Ready to import</div>
                <div class="font-head text-3xl font-bold text-ok-600 mt-1">{{ $ready }}</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl p-4">
                <div class="text-xs text-slate-500">Problems</div>
                <div class="font-head text-3xl font-bold {{ $problems ? 'text-danger-600' : 'text-slate-900' }} mt-1">{{ $problems }}</div>
            </div>
        </div>

        <x-card class="p-0 overflow-hidden" x-data="{ onlyProblems: false }">
            <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-slate-200">
                <div class="min-w-0">
                    <div class="font-head font-bold text-slate-900 truncate">{{ $preview['filename'] }}</div>
                    <p class="text-xs text-slate-500">Rows with a problem are skipped. Fix the file and upload it again to include them.</p>
                </div>
                @if ($problems)
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" x-model="onlyProblems" class="w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-100">
                        Show only problems
                    </label>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Row</th>
                            <th class="px-4 py-3">Student</th>
                            <th class="px-4 py-3 hidden md:table-cell">Programme</th>
                            <th class="px-4 py-3">Result</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            <tr class="align-top {{ $row['errors'] ? 'bg-danger-50/50' : '' }}" @if (! $row['errors']) x-show="! onlyProblems" @endif>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $row['line'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-800">{{ $row['name'] !== '' ? $row['name'] : '(no name)' }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['email'] }} &middot; <span class="font-mono">{{ $row['student_id'] }}</span></div>
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell text-slate-600">{{ $row['programme'] }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['errors'])
                                        <ul class="text-xs text-danger-700 flex flex-col gap-0.5">
                                            @foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach
                                        </ul>
                                    @else
                                        <x-badge color="green">Ready</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3 p-4 border-t border-slate-200">
                <form method="POST" action="{{ route('users.import.cancel') }}">
                    @csrf
                    <x-secondary-button>Cancel</x-secondary-button>
                </form>
                <form method="POST" action="{{ route('users.import.store') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $preview['token'] }}">
                    <x-primary-button :disabled="$ready === 0">Import {{ $ready }} {{ \Illuminate\Support\Str::plural('student', $ready) }}</x-primary-button>
                </form>
            </div>
        </x-card>
    @endif
</x-app-layout>
