@props(['title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'bg-white border border-slate-200 rounded-2xl p-6']) }}>
    @if ($title)
        <h2 class="font-head text-base font-bold text-slate-900 {{ $subtitle ? 'mb-1' : 'mb-4' }}">{{ $title }}</h2>
    @endif
    @if ($subtitle)
        <p class="text-xs text-slate-500 mb-4">{{ $subtitle }}</p>
    @endif
    {{ $slot }}
</div>
