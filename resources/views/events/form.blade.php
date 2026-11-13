@php
    $editing = $event->exists;
    $dt = fn ($v) => old($v, $event->$v?->format('Y-m-d\TH:i'));
@endphp

<x-app-layout :title="$editing ? 'Edit Event' : 'New Event'">
    <x-slot name="header">{{ $editing ? 'Edit Event' : 'New Event' }}</x-slot>
    <x-slot name="actions"><a href="{{ $editing ? route('events.show', $event) : route('events.index') }}" class="text-sm font-semibold text-brand-600">&larr; Back</a></x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ $editing ? route('events.update', $event) : route('events.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div>
                <x-input-label for="title" value="Event Title" />
                <x-text-input id="title" name="title" class="block w-full" :value="old('title', $event->title)" required />
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="3" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-100">{{ old('description', $event->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="level" value="Level" />
                    <select id="level" name="level" class="block w-full min-h-[44px] rounded-lg border-slate-300 text-sm">
                        @foreach (\App\Models\Event::LEVELS as $value => $label)
                            <option value="{{ $value }}" @selected(old('level', $event->level) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('level')" class="mt-2" />
                </div>
                @if ($editing)
                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" class="block w-full min-h-[44px] rounded-lg border-slate-300 text-sm">
                            @foreach (\App\Models\Event::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $event->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                @endif
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="starts_at" value="Starts" />
                    <x-text-input id="starts_at" name="starts_at" type="datetime-local" class="block w-full" :value="$dt('starts_at')" required />
                    <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="ends_at" value="Ends" />
                    <x-text-input id="ends_at" name="ends_at" type="datetime-local" class="block w-full" :value="$dt('ends_at')" required />
                    <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="academic_year" value="Academic Year" />
                    <x-text-input id="academic_year" name="academic_year" class="block w-full font-mono" placeholder="2025/2026" :value="old('academic_year', $event->academic_year)" required />
                    <x-input-error :messages="$errors->get('academic_year')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="semester" value="Semester" />
                    <select id="semester" name="semester" class="block w-full min-h-[44px] rounded-lg border-slate-300 text-sm">
                        @foreach ([1, 2, 3] as $sem)
                            <option value="{{ $sem }}" @selected((int) old('semester', $event->semester) === $sem)>Semester {{ $sem }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('semester')" class="mt-2" />
                </div>
            </div>

            {{-- Location --}}
            <div x-data="{
                    lat: '{{ old('latitude', $event->latitude) }}', lng: '{{ old('longitude', $event->longitude) }}', msg: '',
                    locate() {
                        if (! navigator.geolocation) { this.msg = 'Geolocation is not supported by this browser.'; return }
                        this.msg = 'Locating...';
                        navigator.geolocation.getCurrentPosition(
                            p => { this.lat = p.coords.latitude.toFixed(7); this.lng = p.coords.longitude.toFixed(7); this.msg = 'Location captured.' },
                            () => { this.msg = 'Could not get your location. Enter it manually.' },
                            { enableHighAccuracy: true, timeout: 10000 })
                    } }"
                 class="rounded-xl border border-slate-200 p-4 bg-slate-50 flex flex-col gap-4">
                <div>
                    <x-input-label for="venue" value="Venue" />
                    <x-text-input id="venue" name="venue" class="block w-full" :value="old('venue', $event->venue)" required />
                    <x-input-error :messages="$errors->get('venue')" class="mt-2" />
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="latitude" value="Latitude" />
                        <x-text-input id="latitude" name="latitude" class="block w-full font-mono" x-model="lat" required placeholder="3.5430000" />
                        <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="longitude" value="Longitude" />
                        <x-text-input id="longitude" name="longitude" class="block w-full font-mono" x-model="lng" required placeholder="103.4290000" />
                        <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <x-secondary-button @click="locate()">Use my current location</x-secondary-button>
                    <a x-show="lat && lng" :href="`https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=17/${lat}/${lng}`" target="_blank" class="text-[13px] font-semibold text-brand-600">Preview on map</a>
                    <span class="text-xs text-slate-500" x-text="msg"></span>
                </div>
                <p class="text-xs text-slate-500">Students must be near these coordinates to check in to the event.</p>
            </div>

            <div>
                <x-input-label for="approval_letter" :value="$editing ? 'Approval Letter (leave empty to keep current)' : 'Approval Letter (PDF, JPG or PNG, max 4 MB)'" />
                <input id="approval_letter" type="file" name="approval_letter" accept=".pdf,.jpg,.jpeg,.png" @required(! $editing)
                       class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold hover:file:bg-brand-100">
                <x-input-error :messages="$errors->get('approval_letter')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3 mt-2">
                <a href="{{ $editing ? route('events.show', $event) : route('events.index') }}"><x-secondary-button>Cancel</x-secondary-button></a>
                <x-primary-button>{{ $editing ? 'Save Changes' : 'Create Event' }}</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
