<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center min-h-[44px] px-5 py-2.5 bg-white border border-slate-300 rounded-lg font-semibold text-sm text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2 disabled:opacity-50 transition duration-150']) }}>
    {{ $slot }}
</button>
