<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center min-h-[44px] px-5 py-2.5 bg-danger-600 rounded-lg font-semibold text-sm text-white hover:bg-danger-700 focus:outline-none focus:ring-2 focus:ring-danger-500 focus:ring-offset-2 transition duration-150']) }}>
    {{ $slot }}
</button>
