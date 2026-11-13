@if (session('toast'))
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition.opacity
         role="status"
         class="fixed bottom-5 right-5 z-50 flex items-center gap-2 bg-slate-900 text-white text-sm px-4 py-3 rounded-lg shadow-lg">
        <x-icon name="check" :size="16" class="text-ok-500" />
        {{ session('toast') }}
    </div>
@endif
