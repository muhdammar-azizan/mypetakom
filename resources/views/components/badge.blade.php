@props(['color' => 'brand'])

@php
    $styles = [
        'brand' => 'bg-brand-50 text-brand-700',
        'solid' => 'bg-brand-700 text-white',
        'gold' => 'bg-gold-50 text-gold-700',
        'green' => 'bg-ok-50 text-ok-600',
        'red' => 'bg-danger-50 text-danger-700',
        'gray' => 'bg-slate-100 text-slate-600',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold '.($styles[$color] ?? $styles['brand'])]) }}>{{ $slot }}</span>
