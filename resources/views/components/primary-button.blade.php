<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center min-h-[44px] px-5 py-2.5 bg-brand-700 rounded-lg font-bold text-sm text-white hover:bg-brand-800 hover:-translate-y-px hover:shadow-lg active:translate-y-0 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2 disabled:opacity-50 transition duration-150']) }}>
    {{ $slot }}
</button>
