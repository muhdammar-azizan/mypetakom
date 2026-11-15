@props(['title' => null])

@php
    $user = auth()->user();
    $menu = \App\Support\Menu::for($user);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'MyPetakom') }}{{ $title ? ' - '.$title : '' }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|sora:400,500,600,700,800|roboto-mono:400,500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-800 bg-slate-50">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-brand-600 focus:text-white focus:font-semibold">Skip to content</a>
        <div x-data="{
                collapsed: (() => { try { return localStorage.getItem('sidebar') === '1' } catch (e) { return false } })(),
                drawer: false,
                toggle() { this.collapsed = ! this.collapsed; try { localStorage.setItem('sidebar', this.collapsed ? '1' : '0') } catch (e) {} },
            }"
            class="h-screen flex overflow-hidden relative">

            {{-- Mobile backdrop --}}
            <div x-show="drawer" x-transition.opacity @click="drawer = false" class="fixed inset-0 bg-black/45 z-30 lg:hidden" style="display:none"></div>

            {{-- Sidebar --}}
            <aside :class="[drawer ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', collapsed ? 'lg:w-[72px]' : 'lg:w-60']"
                   class="fixed lg:static inset-y-0 left-0 z-40 w-60 shrink-0 bg-brand-vibrant flex flex-col overflow-hidden transition-all duration-200">
                <div class="flex items-center gap-3 p-4 border-b border-white/10">
                    <div class="w-[38px] h-[38px] rounded-full bg-white shrink-0 flex items-center justify-center shadow">
                        <img src="{{ asset('images/logo.png') }}" alt="Petakom logo" class="w-[34px] h-[34px] rounded-full object-cover">
                    </div>
                    <span x-show="! collapsed || drawer" class="font-head font-bold text-white text-[15px] whitespace-nowrap">MyPetakom</span>
                </div>

                <nav class="flex-1 p-2.5 flex flex-col gap-0.5 overflow-y-auto" aria-label="Main">
                    @foreach ($menu as $item)
                        <a href="{{ $item['url'] }}"
                           @class([
                               'flex items-center gap-3 min-h-[44px] px-3 py-2.5 rounded-lg border-l-[3px] whitespace-nowrap transition',
                               'bg-white/15 text-white border-gold-500 font-semibold' => $item['active'],
                               'text-white/75 border-transparent hover:bg-white/10' => ! $item['active'],
                           ])
                           @if ($item['active']) aria-current="page" @endif
                           title="{{ $item['label'] }}">
                            <x-icon :name="$item['icon']" class="shrink-0" />
                            <span x-show="! collapsed || drawer" class="text-sm">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="hidden lg:block p-2.5 border-t border-white/10">
                    <button type="button" @click="toggle()" class="w-full min-h-[44px] flex items-center justify-center gap-2 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-gold-500">
                        <x-icon name="collapse" />
                        <span x-show="! collapsed" class="text-[13px]" x-text="'Collapse'"></span>
                    </button>
                </div>
            </aside>

            {{-- Main column --}}
            <div class="flex-1 flex flex-col min-w-0">
                {{-- Topbar --}}
                <header class="h-16 shrink-0 flex items-center gap-3 px-5 bg-white border-b border-slate-200">
                    <button type="button" @click="drawer = true" aria-label="Open navigation menu"
                            class="lg:hidden w-11 h-11 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600">
                        <x-icon name="menu" :size="19" />
                    </button>

                    <div class="flex-1"></div>

                    @php
                        $unread = $user->unreadNotifications()->count();
                        $latest = $user->notifications()->limit(5)->get();
                    @endphp
                    <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" class="relative">
                        <button type="button" @click="open = ! open" aria-label="Notifications{{ $unread ? ', '.$unread.' unread' : '' }}" :aria-expanded="open"
                                class="relative w-11 h-11 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:-translate-y-0.5 transition">
                            <x-icon name="bell" />
                            @if ($unread)
                                <span class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 rounded-full bg-danger-600 text-white text-[10.5px] font-bold flex items-center justify-center">{{ $unread > 9 ? '9+' : $unread }}</span>
                            @endif
                        </button>

                        <div x-show="open" x-transition style="display:none"
                             class="absolute right-0 top-14 w-[min(22rem,calc(100vw-2rem))] bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden z-50">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                                <span class="font-head font-semibold text-sm text-slate-900">Notifications</span>
                                @if ($unread)
                                    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                                        <button class="text-xs font-semibold text-brand-600 hover:underline">Mark all as read</button>
                                    </form>
                                @endif
                            </div>
                            @forelse ($latest as $n)
                                <form method="POST" action="{{ route('notifications.read', $n->id) }}">@csrf
                                    <button class="w-full text-left px-4 py-3 border-b border-slate-100 last:border-b-0 hover:bg-slate-50 flex gap-2.5">
                                        <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 {{ $n->read_at ? 'bg-transparent' : 'bg-brand-600' }}"></span>
                                        <span class="min-w-0">
                                            <span class="block text-[13px] {{ $n->read_at ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $n->data['title'] }}</span>
                                            <span class="block text-xs text-slate-500 truncate">{{ $n->data['body'] }}</span>
                                            <span class="block text-[11px] text-slate-400 mt-0.5">{{ $n->created_at->diffForHumans() }}</span>
                                        </span>
                                    </button>
                                </form>
                            @empty
                                <p class="px-4 py-8 text-center text-sm text-slate-500">No notifications yet.</p>
                            @endforelse
                            <a href="{{ route('notifications.index') }}" class="block px-4 py-2.5 text-center text-[13px] font-semibold text-brand-600 bg-slate-50 hover:bg-slate-100">View all</a>
                        </div>
                    </div>

                    <div x-data="{ open: false }" @click.outside="open = false" class="relative pl-3 border-l border-slate-200">
                        <button type="button" @click="open = ! open" class="flex items-center gap-2.5 min-h-[44px] p-1 hover:-translate-y-0.5 transition focus:outline-none">
                            <span class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-head font-bold text-[13px] shrink-0">{{ $user->initials }}</span>
                            <span class="hidden sm:block text-left leading-tight min-w-0">
                                <span class="block text-[13px] font-semibold text-slate-800 truncate max-w-[160px]">{{ $user->name }}</span>
                                <span class="block text-[11.5px] text-slate-500 truncate">{{ $user->role_label }}</span>
                            </span>
                            <x-icon name="chevron-down" :size="14" class="text-slate-400" />
                        </button>

                        <div x-show="open" x-transition style="display:none"
                             class="absolute right-0 top-14 w-44 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden z-50">
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-[13.5px] text-slate-700 hover:bg-slate-50">View Profile</a>
                            <a href="{{ route('settings.edit') }}" class="block px-4 py-2.5 text-[13.5px] text-slate-700 border-t border-slate-100 hover:bg-slate-50">Settings</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2.5 text-[13.5px] text-danger-600 border-t border-slate-100 hover:bg-danger-50">Log Out</button>
                            </form>
                        </div>
                    </div>
                </header>

                {{-- Breadcrumb --}}
                <nav class="flex items-center gap-2 px-5 py-3 bg-white border-b border-slate-200 text-[13px] text-slate-500 shrink-0" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="hover:text-slate-800">Dashboard</a>
                    @if ($title && $title !== 'Dashboard')
                        <x-icon name="chevron-right" :size="13" />
                        <span class="text-slate-800 font-semibold">{{ $title }}</span>
                    @endif
                </nav>

                {{-- Content --}}
                <main id="main" tabindex="-1" class="focus:outline-none flex-1 overflow-auto px-4 sm:px-6 pt-7 pb-16">
                    <div class="max-w-[1040px] mx-auto">
                        @isset($header)
                            <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
                                <h1 class="font-head text-[26px] font-bold text-slate-900">{{ $header }}</h1>
                                @isset($actions)
                                    <div>{{ $actions }}</div>
                                @endisset
                            </div>
                        @endisset

                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        <x-toast />
    </body>
</html>
