@php
    $editing = $account->exists;
    $role = old('role', $account->role);
@endphp

<x-app-layout :title="$editing ? 'Edit Account' : 'New Account'">
    <x-slot name="header">{{ $editing ? 'Edit Account' : 'New Account' }}</x-slot>
    <x-slot name="actions"><a href="{{ route('users.index') }}" class="text-sm font-semibold text-brand-600">&larr; Back to accounts</a></x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $editing ? route('users.update', $account) : route('users.store') }}" class="flex flex-col gap-4"
              x-data="{ role: '{{ $role }}' }">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div>
                <x-input-label for="role" value="Role" />
                <select id="role" name="role" x-model="role" class="block w-full min-h-[44px] rounded-lg border-slate-300 text-sm">
                    <option value="student">Student</option>
                    <option value="advisor">Event Advisor</option>
                    <option value="coordinator">Petakom Coordinator</option>
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="name" value="Full Name" />
                <x-text-input id="name" name="name" class="block w-full" :value="old('name', $account->name)" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="block w-full" :value="old('email', $account->email)" required />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div x-show="role === 'student'">
                <x-input-label for="student_id" value="Student ID" />
                <x-text-input id="student_id" name="student_id" class="block w-full font-mono uppercase" :value="old('student_id', $account->student_id)" />
                <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
            </div>

            <div x-show="role !== 'student'" style="display:none">
                <x-input-label for="staff_id" value="Staff ID" />
                <x-text-input id="staff_id" name="staff_id" class="block w-full font-mono uppercase" :value="old('staff_id', $account->staff_id)" />
                <x-input-error :messages="$errors->get('staff_id')" class="mt-2" />
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="phone" value="Phone Number" />
                    <x-text-input id="phone" name="phone" type="tel" class="block w-full" :value="old('phone', $account->phone)" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="programme">
                        <span x-text="role === 'coordinator' ? 'Department' : 'Programme'"></span>
                    </x-input-label>
                    <x-text-input id="programme" name="programme" class="block w-full" :value="old('programme', $account->programme)" />
                    <x-input-error :messages="$errors->get('programme')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="password" :value="$editing ? 'New Password (leave blank to keep current)' : 'Initial Password'" />
                <x-text-input id="password" name="password" type="password" class="block w-full" autocomplete="new-password" :required="! $editing" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3 mt-2">
                <a href="{{ route('users.index') }}"><x-secondary-button>Cancel</x-secondary-button></a>
                <x-primary-button>{{ $editing ? 'Save Changes' : 'Create Account' }}</x-primary-button>
            </div>
        </form>

        @if ($editing && $account->hasTwoFactorEnabled())
            <div class="mt-6 pt-5 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-slate-600">
                    <span class="font-semibold text-slate-800">Two-factor sign-in is on.</span>
                    If this person lost their phone and recovery codes, reset it. They can set it up again afterwards.
                </div>
                <form method="POST" action="{{ route('users.two-factor.reset', $account) }}" onsubmit="return confirm('Reset two-factor sign-in for {{ e($account->name) }}?')">
                    @csrf
                    <x-secondary-button>Reset two-factor</x-secondary-button>
                </form>
            </div>
        @endif
    </x-card>
</x-app-layout>
