@php
    $editing = $slot->exists;
    $dt = fn ($f) => old($f, $slot->$f?->format('Y-m-d\TH:i'));
@endphp

<x-app-layout :title="$editing ? 'Edit Slot' : 'New Slot'">
    <x-slot name="header">{{ $editing ? 'Edit Attendance Slot' : 'New Attendance Slot' }}</x-slot>
    <x-slot name="actions"><a href="{{ $editing ? route('attendance.show', $slot) : route('attendance.index') }}" class="text-sm font-semibold text-brand-600">&larr; Back</a></x-slot>

    <x-card class="max-w-2xl">
        @if ($events->isEmpty())
            <p class="text-sm text-slate-600">You need an active event before you can create an attendance slot. <a href="{{ route('events.create') }}" class="font-semibold text-brand-600">Create an event</a>.</p>
        @else
            <form method="POST" action="{{ $editing ? route('attendance.update', $slot) : route('attendance.store') }}" class="flex flex-col gap-4">
                @csrf
                @if ($editing) @method('PUT') @endif

                <div>
                    <x-input-label for="event_id" value="Event" />
                    <select id="event_id" name="event_id" class="block w-full min-h-[44px] rounded-lg border-slate-300 text-sm" required>
                        <option value="">Choose an event</option>
                        @foreach ($events as $e)
                            <option value="{{ $e->id }}" @selected((int) old('event_id', $slot->event_id) === $e->id)>{{ $e->title }} ({{ $e->starts_at->format('d M Y') }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('event_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="title" value="Slot Title" />
                    <x-text-input id="title" name="title" class="block w-full" :value="old('title', $slot->title)" placeholder="Morning session" required />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="opens_at" value="Opens" />
                        <x-text-input id="opens_at" name="opens_at" type="datetime-local" class="block w-full" :value="$dt('opens_at')" required />
                        <x-input-error :messages="$errors->get('opens_at')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="closes_at" value="Closes" />
                        <x-text-input id="closes_at" name="closes_at" type="datetime-local" class="block w-full" :value="$dt('closes_at')" required />
                        <x-input-error :messages="$errors->get('closes_at')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="radius_m" value="Allowed distance from the venue (metres)" />
                    <x-text-input id="radius_m" name="radius_m" type="number" min="20" max="1000" class="block w-full" :value="old('radius_m', $slot->radius_m)" required />
                    <p class="mt-1 text-xs text-slate-500">Students must be within this distance of the event location (20 - 1000 m).</p>
                    <x-input-error :messages="$errors->get('radius_m')" class="mt-2" />
                </div>

                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="rotating_qr" value="1" @checked(old('rotating_qr', $editing ? $slot->rotating_qr : true)) class="mt-1 w-5 h-5 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-100">
                    <span>
                        <span class="block text-sm font-semibold text-slate-800">Rotating QR code</span>
                        <span class="block text-xs text-slate-500">The code changes every 30 seconds, so a photo of it cannot be shared with people who are not at the venue. Show it on a screen. Turn this off if you want to print the QR.</span>
                    </span>
                </label>

                <div class="flex justify-end gap-3 mt-2">
                    <a href="{{ $editing ? route('attendance.show', $slot) : route('attendance.index') }}"><x-secondary-button>Cancel</x-secondary-button></a>
                    <x-primary-button>{{ $editing ? 'Save Changes' : 'Create Slot' }}</x-primary-button>
                </div>
            </form>
        @endif
    </x-card>
</x-app-layout>
