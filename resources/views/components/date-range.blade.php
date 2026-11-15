{{-- "From" and "To" date inputs for list filters. Read by App\Support\DateFilter. --}}
<div class="flex flex-wrap items-center gap-2">
    <input type="date" name="from" value="{{ request('from') }}" aria-label="From date" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
    <span class="text-slate-400 text-sm" aria-hidden="true">to</span>
    <input type="date" name="to" value="{{ request('to') }}" aria-label="To date" class="min-h-[44px] rounded-lg border-slate-300 text-sm">
</div>
@error('to')
    <p class="basis-full text-sm text-danger-700">{{ $message }}</p>
@enderror
