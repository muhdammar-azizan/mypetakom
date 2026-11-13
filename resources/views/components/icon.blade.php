@props(['name', 'size' => 18])

@php
    $paths = [
        'home' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v9h14v-9"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.5 3-6 6.5-6s6.5 2.5 6.5 6"/><circle cx="18" cy="9" r="2.6"/><path d="M15.5 20c.2-2.8 1.9-4.7 3.7-5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="M9 15l2 2 4-4"/>',
        'award' => '<circle cx="12" cy="8" r="5.2"/><path d="M8.5 12.5L7 21l5-3 5 3-1.5-8.5"/>',
        'file' => '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5"/><path d="M9 14l2 2 4-4"/>',
        'clipboard' => '<rect x="6" y="4" width="12" height="17" rx="2"/><rect x="9" y="2.5" width="6" height="3" rx="1"/><path d="M9 11h6M9 15h6"/>',
        'chart' => '<path d="M4 20V10M12 20V4M20 20v-7"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'bell' => '<path d="M6 10a6 6 0 1112 0c0 4 1.5 5.5 1.5 5.5H4.5S6 14 6 10z"/><path d="M10 19a2 2 0 004 0"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
        'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
        'collapse' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M14 10l-2 2 2 2"/>',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/>',
        'mail' => '<path d="M2 6a2 2 0 012-2h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/><path d="M22 7l-9.5 6.5a2 2 0 01-2 0L2 7"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
    ];
@endphp

<svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>{!! $paths[$name] ?? '' !!}</svg>
