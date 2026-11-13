@php $editing = $claim->exists; @endphp

<x-app-layout :title="$editing ? 'Edit Claim' : 'New Claim'">
    <x-slot name="header">{{ $editing ? 'Edit Merit Claim' : 'New Merit Claim' }}</x-slot>
    <x-slot name="actions"><a href="{{ $editing ? route('merit-claims.show', $claim) : route('merit-claims.index') }}" class="text-sm font-semibold text-brand-600">&larr; Back</a></x-slot>

    <x-card class="max-w-2xl">
        @if ($events->isEmpty())
            <p class="text-sm text-slate-600">There are no events you can claim merit for right now.</p>
        @else
            <p class="text-sm text-slate-600 mb-4">Were you part of an event but your merit is missing? Upload a supporting letter and the event advisor will review your claim.</p>

            <form method="POST" action="{{ $editing ? route('merit-claims.update', $claim) : route('merit-claims.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                @csrf
                @if ($editing) @method('PUT') @endif

                <div>
                    <x-input-label for="event_id" value="Event" />
                    <select id="event_id" name="event_id" class="block w-full min-h-[44px] rounded-lg border-slate-300 text-sm disabled:bg-slate-100" required @disabled($editing)>
                        <option value="">Choose an event</option>
                        @foreach ($events as $e)
                            <option value="{{ $e->id }}" @selected((int) old('event_id', $claim->event_id) === $e->id)>{{ $e->title }} ({{ $e->level_label }}, {{ $e->starts_at->format('d M Y') }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('event_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="role" value="Your role in the event" />
                    <select id="role" name="role" class="block w-full min-h-[44px] rounded-lg border-slate-300 text-sm">
                        @foreach (\App\Models\Merit::ROLES as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $claim->role) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="reason" value="Explanation (optional)" />
                    <textarea id="reason" name="reason" rows="3" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-100">{{ old('reason', $claim->reason) }}</textarea>
                    <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="letter" :value="$editing ? 'Supporting letter (leave empty to keep current)' : 'Supporting letter (PDF, JPG or PNG, max 4 MB)'" />
                    <input id="letter" type="file" name="letter" accept=".pdf,.jpg,.jpeg,.png" @required(! $editing)
                           class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold hover:file:bg-brand-100">
                    <x-input-error :messages="$errors->get('letter')" class="mt-2" />
                </div>

                <div class="flex justify-end gap-3 mt-2">
                    <a href="{{ $editing ? route('merit-claims.show', $claim) : route('merit-claims.index') }}"><x-secondary-button>Cancel</x-secondary-button></a>
                    <x-primary-button>{{ $editing ? 'Save Changes' : 'Save as draft' }}</x-primary-button>
                </div>
            </form>
        @endif
    </x-card>
</x-app-layout>
