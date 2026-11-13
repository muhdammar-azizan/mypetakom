@php
    $event = $slot->event;
    $done = session('checkin');
    $blocked = in_array($event->status, ['cancelled', 'postponed'], true) ? "This event has been {$event->status}." : null;
    $window = $slot->window;
@endphp

<x-public-layout title="Check-in">
    <div class="flex flex-wrap gap-2 mb-3">
        <x-badge :color="$event->status_color">{{ $event->status_label }}</x-badge>
        <x-badge :color="$slot->window_color">{{ ucfirst($window) }}</x-badge>
    </div>
    <h1 class="font-head text-2xl font-bold text-slate-900">{{ $event->title }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ $slot->title }}</p>

    @if ($event->description)
        <p class="mt-3 text-sm text-slate-700 whitespace-pre-line">{{ $event->description }}</p>
    @endif

    <dl class="mt-4 flex flex-col gap-2 text-sm">
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Venue</dt><dd class="font-medium text-right">{{ $event->venue }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Venue location</dt><dd class="font-mono text-xs text-right">{{ $event->latitude }}, {{ $event->longitude }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Check-in window</dt><dd class="font-medium text-right">{{ $slot->opens_at->format('d M, h:i A') }} - {{ $slot->closes_at->format('h:i A') }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Allowed distance</dt><dd class="font-medium">{{ $slot->radius_m }} m</dd></div>
    </dl>

    @if ($done)
        <div role="status" class="mt-6 px-4 py-4 bg-ok-50 border border-ok-500 rounded-xl text-ok-600">
            <div class="flex items-center gap-2 font-semibold"><x-icon name="check" /> Check-in successful</div>
            <p class="mt-1 text-sm text-slate-700">{{ $done['name'] }} <span class="font-mono text-xs">({{ $done['student_id'] }})</span><br>
                {{ $done['time'] }} &middot; {{ $done['distance'] }} m from the venue</p>
        </div>
    @elseif ($blocked)
        <p role="alert" class="mt-6 px-4 py-3 rounded-lg text-sm font-semibold {{ $event->status === 'cancelled' ? 'bg-danger-50 text-danger-700' : 'bg-gold-50 text-gold-700' }}">{{ $blocked }} Check-in is not available.</p>
    @elseif ($window !== 'open')
        <p role="alert" class="mt-6 px-4 py-3 rounded-lg text-sm font-semibold bg-slate-100 text-slate-600">
            {{ $window === 'upcoming' ? 'This slot has not opened yet.' : 'This slot has closed.' }}
        </p>
    @else
        <form method="POST" action="{{ route('checkin.store', $slot->qr_token) }}" class="mt-6 flex flex-col gap-4"
              x-data="{
                  lat: '{{ old('latitude') }}', lng: '{{ old('longitude') }}', state: '{{ old('latitude') ? 'ready' : 'asking' }}',
                  locate() {
                      this.state = 'asking';
                      if (! navigator.geolocation) { this.state = 'error'; return }
                      navigator.geolocation.getCurrentPosition(
                          p => { this.lat = p.coords.latitude.toFixed(7); this.lng = p.coords.longitude.toFixed(7); this.state = 'ready' },
                          () => { this.state = 'error' },
                          { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 })
                  } }"
              x-init="locate()">
            @csrf
            <input type="hidden" name="latitude" :value="lat">
            <input type="hidden" name="longitude" :value="lng">

            <div class="rounded-lg border px-3.5 py-3 text-sm"
                 :class="state === 'ready' ? 'border-ok-500 bg-ok-50 text-ok-600' : (state === 'error' ? 'border-danger-500 bg-danger-50 text-danger-700' : 'border-slate-200 bg-slate-50 text-slate-600')">
                <template x-if="state === 'asking'"><span>Getting your location... please allow location access.</span></template>
                <template x-if="state === 'ready'"><span>Your location: <span class="font-mono text-xs" x-text="lat + ', ' + lng"></span></span></template>
                <template x-if="state === 'error'">
                    <span>Could not get your location. Turn on location access in your browser, then
                        <button type="button" @click="locate()" class="font-semibold underline">try again</button>.</span>
                </template>
            </div>

            <div>
                <x-input-label for="student_id" value="Student ID" />
                <x-text-input id="student_id" name="student_id" class="block w-full font-mono uppercase" :value="old('student_id')" required autocomplete="username" placeholder="CB21091" />
                <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" value="Password" />
                <x-text-input id="password" name="password" type="password" class="block w-full" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            @if ($errors->has('checkin') || $errors->has('latitude'))
                <div role="alert" class="flex items-start gap-2.5 px-3.5 py-3 bg-danger-50 border border-danger-500 rounded-lg">
                    <x-icon name="x-circle" class="shrink-0 text-danger-700 mt-0.5" />
                    <span class="text-[13.5px] text-danger-700 leading-snug">{{ $errors->first('checkin') ?: $errors->first('latitude') }}</span>
                </div>
            @endif

            <x-primary-button class="w-full" x-bind:disabled="state !== 'ready'">Check in</x-primary-button>
        </form>
    @endif

    <a href="{{ $event->map_url }}" target="_blank" class="mt-5 inline-flex w-full items-center justify-center min-h-[44px] rounded-lg border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">View venue on map</a>
</x-public-layout>
