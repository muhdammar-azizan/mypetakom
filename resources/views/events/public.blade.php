<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $event->title }} - MyPetakom</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|sora:600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-800">
        <main class="min-h-screen flex items-center justify-center p-4">
            <div class="w-full max-w-md bg-white border border-slate-200 rounded-3xl shadow-md p-6 sm:p-8">
                <div class="flex items-center gap-2.5 mb-6">
                    <img src="{{ asset('images/logo.png') }}" alt="Petakom logo" class="w-11 h-11 object-contain">
                    <span class="font-head font-bold text-slate-900 text-lg">MyPetakom</span>
                </div>

                <div class="flex flex-wrap gap-2 mb-3">
                    <x-badge :color="$event->status_color">{{ $event->status_label }}</x-badge>
                    <x-badge color="brand">{{ $event->level_label }}</x-badge>
                </div>
                <h1 class="font-head text-2xl font-bold text-slate-900">{{ $event->title }}</h1>

                @if (in_array($event->status, ['postponed', 'cancelled']))
                    <p class="mt-3 px-3 py-2 rounded-lg text-sm font-semibold {{ $event->status === 'cancelled' ? 'bg-danger-50 text-danger-700' : 'bg-gold-50 text-gold-700' }}">This event has been {{ $event->status }}.</p>
                @endif

                @if ($event->description)
                    <p class="mt-4 text-sm text-slate-700 whitespace-pre-line">{{ $event->description }}</p>
                @endif

                <dl class="mt-5 flex flex-col gap-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Starts</dt><dd class="font-medium text-right">{{ $event->starts_at->format('d M Y, h:i A') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Ends</dt><dd class="font-medium text-right">{{ $event->ends_at->format('d M Y, h:i A') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Venue</dt><dd class="font-medium text-right">{{ $event->venue }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Advisor</dt><dd class="font-medium text-right">{{ $event->advisor->name }}</dd></div>
                </dl>

                <a href="{{ $event->map_url }}" target="_blank" class="mt-6 inline-flex w-full items-center justify-center min-h-[44px] rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">View location on map</a>
            </div>
        </main>
    </body>
</html>
