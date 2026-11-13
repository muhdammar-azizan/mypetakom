<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'MyPetakom') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|sora:400,500,600,700,800|roboto-mono:400,500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-800 antialiased bg-slate-100">
        <div class="min-h-screen flex flex-wrap gap-5 p-5">

            {{-- Form panel --}}
            <div class="flex-1 basis-[380px] min-h-[320px] flex items-center justify-center px-4 py-8 sm:px-8">
                <div class="w-full max-w-[380px]">
                    <a href="/" class="flex items-center gap-2.5 mb-10">
                        <img src="{{ asset('images/logo.png') }}" alt="Petakom logo" class="w-[52px] h-[52px] object-contain">
                        <span class="font-head font-bold text-slate-900 text-[22px]">MyPetakom</span>
                    </a>

                    {{ $slot }}
                </div>
            </div>

            {{-- Logo panel --}}
            <div class="hidden lg:flex flex-[1.15_1_420px] min-h-[560px] items-center justify-center p-10 bg-white border border-slate-200 rounded-3xl shadow-md">
                <img src="{{ asset('images/logo.png') }}" alt="Petakom logo" class="w-full h-full object-contain">
            </div>
        </div>
    </body>
</html>
