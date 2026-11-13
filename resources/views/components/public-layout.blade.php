@props(['title' => 'MyPetakom'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }} - MyPetakom</title>
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
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
