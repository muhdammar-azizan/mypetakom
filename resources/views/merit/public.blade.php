<x-public-layout title="Student Merit">
    <div class="flex items-center gap-4 mb-5">
        @if ($student->avatar_url)
            <img src="{{ $student->avatar_url }}" alt="" class="w-16 h-16 rounded-full object-cover border border-slate-200">
        @else
            <div class="w-16 h-16 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-head font-bold text-xl">{{ $student->initials }}</div>
        @endif
        <div class="min-w-0">
            <h1 class="font-head text-xl font-bold text-slate-900">{{ $student->name }}</h1>
            <p class="text-sm text-slate-500 font-mono">{{ $student->student_id }}</p>
            @if ($student->programme)<p class="text-xs text-slate-500">{{ $student->programme }}</p>@endif
        </div>
    </div>

    <div class="rounded-2xl bg-brand-vibrant text-white px-5 py-4 mb-5">
        <div class="text-xs uppercase tracking-wide text-white/70">Total merit</div>
        <div class="font-head text-4xl font-bold">{{ $total }}</div>
    </div>

    <h2 class="font-head text-sm font-bold text-slate-900 mb-2">Merit per academic year</h2>
    <div class="mb-5">
        @forelse ($years as $y)
            <div class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 text-sm">
                <span class="text-slate-700">{{ $y['academic_year'] }}</span>
                <span class="font-mono font-semibold text-slate-900">{{ $y['points'] }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-500">No merit recorded yet.</p>
        @endforelse
    </div>

    <h2 class="font-head text-sm font-bold text-slate-900 mb-2">Merit per semester</h2>
    @forelse ($semesters as $s)
        <div class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 text-sm">
            <span class="text-slate-700">{{ $s['academic_year'] }} &middot; Sem {{ $s['semester'] }}</span>
            <span class="font-mono"><span class="text-slate-800">+{{ $s['points'] }}</span> <span class="text-xs text-slate-500">(cum. {{ $s['cumulative'] }})</span></span>
        </div>
    @empty
        <p class="text-sm text-slate-500">No merit recorded yet.</p>
    @endforelse
</x-public-layout>
