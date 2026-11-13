@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'min-h-[44px] border-slate-300 text-sm text-slate-800 focus:border-brand-600 focus:ring-2 focus:ring-brand-100 rounded-lg disabled:bg-slate-100 disabled:text-slate-500']) }}>
