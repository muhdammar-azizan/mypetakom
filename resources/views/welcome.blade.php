@php
    $roles = [
        ['users', 'Student', 'Apply for membership, check in to events from your phone, track your merit and share a merit QR.'],
        ['calendar', 'Event Advisor', 'Create events, apply for merit, open attendance slots with a rotating QR and review merit claims.'],
        ['clipboard', 'Petakom Coordinator', 'Approve memberships and merit applications, manage accounts and watch the whole association in one dashboard.'],
    ];
    $modules = [
        ['users', 'Membership', 'Student card upload, review and approval, with every account managed by the coordinator.'],
        ['calendar', 'Events and merit', 'Events with level, map location and committee, plus merit applications reviewed by the coordinator.'],
        ['check', 'Attendance', 'Time-windowed QR check-in that also checks your GPS distance to the venue.'],
        ['award', 'Merit', 'Points by event level and role, merit claims for anything missing, and a public merit QR.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>MyPetakom - Persatuan Petakom, FK UMPSA</title>
        <meta name="description" content="MyPetakom manages membership, events, location-verified attendance and merit for Persatuan Petakom, FK UMPSA.">
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|sora:600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-800">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:m-3 focus:px-3 focus:py-2 focus:bg-white focus:rounded-lg focus:text-brand-700">Skip to content</a>

        {{-- Top bar --}}
        <header class="max-w-6xl mx-auto px-5 py-5 flex items-center justify-between gap-4">
            <a href="/" class="flex items-center gap-2.5">
                <img src="{{ asset('images/logo.png') }}" alt="" class="w-11 h-11 object-contain">
                <span class="font-head font-bold text-slate-900 text-xl">MyPetakom</span>
            </a>
            <nav class="flex items-center gap-2 sm:gap-3" aria-label="Account">
                @auth
                    <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold">Go to dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="px-4 py-2 rounded-xl text-sm font-semibold text-brand-700 hover:bg-white">Log in</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold">Register</a>
                    @endif
                @endauth
            </nav>
        </header>

        <main id="main">
            {{-- Hero --}}
            <section class="max-w-6xl mx-auto px-5 pt-6 pb-14 sm:pt-12">
                <div class="grid lg:grid-cols-[1.2fr_1fr] gap-8 items-center bg-white border border-slate-200 rounded-3xl shadow-md p-6 sm:p-12">
                    <div>
                        <p class="inline-block px-3 py-1 rounded-full bg-brand-50 text-brand-700 text-xs font-semibold tracking-wide">Persatuan Petakom &middot; FK UMPSA</p>
                        <h1 class="mt-4 font-head font-extrabold text-slate-900 text-3xl sm:text-5xl leading-tight">One place for membership, events and merit.</h1>
                        <p class="mt-4 text-slate-600 text-base sm:text-lg max-w-xl">
                            Join the association, check in to events from your phone, and watch your merit grow. Advisors run events, coordinators keep everything fair and on record.
                        </p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            @auth
                                <a href="{{ route('dashboard') }}" class="px-6 py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold">Go to dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="px-6 py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold">Log in</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="px-6 py-3 rounded-xl bg-white hover:bg-slate-50 border border-slate-300 text-slate-800 font-semibold">Create a student account</a>
                                @endif
                            @endauth
                        </div>
                    </div>
                    <div class="hidden lg:flex items-center justify-center">
                        <img src="{{ asset('images/logo.png') }}" alt="Petakom logo" class="w-full max-w-[320px] object-contain">
                    </div>
                </div>
            </section>

            {{-- Roles --}}
            <section class="max-w-6xl mx-auto px-5 pb-14" aria-labelledby="roles-title">
                <h2 id="roles-title" class="font-head font-bold text-slate-900 text-2xl">Built for three roles</h2>
                <p class="mt-1 text-slate-600">Everyone sees only what they need.</p>
                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    @foreach ($roles as [$icon, $name, $text])
                        <article class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                            <span class="w-11 h-11 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center"><x-icon :name="$icon" :size="22" /></span>
                            <h3 class="mt-4 font-head font-semibold text-slate-900 text-lg">{{ $name }}</h3>
                            <p class="mt-1.5 text-sm text-slate-600">{{ $text }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            {{-- Modules --}}
            <section class="max-w-6xl mx-auto px-5 pb-14" aria-labelledby="modules-title">
                <h2 id="modules-title" class="font-head font-bold text-slate-900 text-2xl">What it does</h2>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($modules as [$icon, $name, $text])
                        <article class="bg-white border border-slate-200 rounded-2xl p-5">
                            <span class="w-9 h-9 rounded-lg bg-gold-50 text-gold-700 flex items-center justify-center"><x-icon :name="$icon" :size="19" /></span>
                            <h3 class="mt-3 font-semibold text-slate-900">{{ $name }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $text }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            {{-- How check-in works --}}
            <section class="max-w-6xl mx-auto px-5 pb-16" aria-labelledby="how-title">
                <div class="bg-brand-800 text-white rounded-3xl p-6 sm:p-10">
                    <h2 id="how-title" class="font-head font-bold text-2xl">How attendance stays honest</h2>
                    <ol class="mt-6 grid gap-5 md:grid-cols-4 text-sm">
                        @foreach (['Scan the event QR, which changes every 30 seconds.', 'Enter your student ID and password.', 'Your phone shares its location, checked against the venue.', 'Merit is added to your record, once, automatically.'] as $i => $step)
                            <li class="flex gap-3">
                                <span class="shrink-0 w-7 h-7 rounded-full bg-gold-500 text-brand-900 font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                                <span class="text-brand-100">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        </main>

        <footer class="max-w-6xl mx-auto px-5 pb-10 text-center text-sm text-slate-500">
            &copy; {{ date('Y') }} Persatuan Petakom, Fakulti Komputeran, UMPSA.
        </footer>
    </body>
</html>
