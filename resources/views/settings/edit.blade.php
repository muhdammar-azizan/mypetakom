@php
    $switches = [
        ['notify_in_app', 'In-app notifications', 'Show notices in the bell at the top of the page.'],
        ['notify_email', 'E-mail notifications', 'Also send important notices to your e-mail address.'],
    ];
@endphp

<x-app-layout title="Settings">
    <x-slot name="header">Settings</x-slot>
    <x-slot name="actions"><a href="{{ route('profile.edit') }}" class="text-sm font-semibold text-brand-600">My profile &rarr;</a></x-slot>

    <div class="flex flex-col gap-5 max-w-3xl">

        {{-- Preferences --}}
        <x-card title="Preferences" subtitle="Choose what you receive and what others can see.">
            <form method="POST" action="{{ route('settings.preferences') }}" class="flex flex-col gap-1">
                @csrf @method('PATCH')

                @foreach ($switches as [$name, $label, $help])
                    <label class="flex items-start justify-between gap-4 py-3 border-t border-slate-100 first:border-t-0 cursor-pointer">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-800">{{ $label }}</span>
                            <span class="block text-xs text-slate-500">{{ $help }}</span>
                        </span>
                        <input type="checkbox" name="{{ $name }}" value="1" @checked($user->$name) class="mt-1 w-5 h-5 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-100">
                    </label>
                @endforeach

                @if ($user->isStudent())
                    <label class="flex items-start justify-between gap-4 py-3 border-t border-slate-100 cursor-pointer">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-800">Show me on the leaderboard</span>
                            <span class="block text-xs text-slate-500">When off, your name is hidden and shown as "Anonymous student".</span>
                        </span>
                        <input type="checkbox" name="show_on_leaderboard" value="1" @checked($user->show_on_leaderboard) class="mt-1 w-5 h-5 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-100">
                    </label>
                @endif

                <div class="flex justify-end pt-3">
                    <x-primary-button>Save settings</x-primary-button>
                </div>
            </form>
        </x-card>

        {{-- Password --}}
        <x-card title="Change Password" x-data="{ pw: '' }">
            <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4">
                @csrf @method('PUT')

                <div>
                    <x-input-label for="current_password" value="Current Password" />
                    <x-text-input id="current_password" name="current_password" type="password" class="block w-full" autocomplete="current-password" />
                    <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="new_password" value="New Password" />
                    <x-text-input id="new_password" name="password" type="password" class="block w-full" autocomplete="new-password" x-model="pw" />
                    <div x-show="pw.length" class="mt-2" style="display:none"
                         x-data="{ get score() { let s = 0; if (pw.length >= 8) s++; if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++; if (/\d/.test(pw) && /[^A-Za-z0-9]/.test(pw)) s++; return s } }">
                        <div class="h-1.5 rounded-full bg-slate-200 overflow-hidden">
                            <div class="h-full transition-all"
                                 :class="score <= 1 ? 'bg-danger-600' : (score == 2 ? 'bg-gold-500' : 'bg-ok-600')"
                                 :style="`width:${[10, 33, 66, 100][score]}%`"></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-500" x-text="['Weak', 'Weak', 'Medium', 'Strong'][score]"></p>
                    </div>
                    <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" value="Confirm New Password" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="block w-full" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                </div>

                <div class="flex justify-end">
                    <x-primary-button>Update Password</x-primary-button>
                </div>
            </form>
        </x-card>

        @if ($twoFactor)
            @include('settings._two-factor')
        @endif

        {{-- Delete account --}}
        <x-card title="Delete account" subtitle="This cannot be undone.">
            @if ($deleteBlocked)
                <p class="text-sm text-slate-600">{{ $deleteBlocked }}</p>
            @else
                <div x-data="{ open: {{ $errors->deleteAccount->isNotEmpty() ? 'true' : 'false' }} }">
                    <p class="text-sm text-slate-600 mb-4">
                        Your profile, membership and
                        @if ($user->isStudent()) merit records @else account details @endif
                        will be removed for good.
                    </p>
                    <x-danger-button type="button" x-show="! open" @click="open = true">Delete my account</x-danger-button>

                    <form x-show="open" style="display:none" method="POST" action="{{ route('settings.account.destroy') }}" class="flex flex-col gap-3 max-w-sm"
                          onsubmit="return confirm('Delete your account for good?')">
                        @csrf @method('DELETE')
                        <div>
                            <x-input-label for="delete_password" value="Confirm with your password" />
                            <x-text-input id="delete_password" name="password" type="password" class="block w-full" autocomplete="current-password" required />
                            <x-input-error :messages="$errors->deleteAccount->get('password')" class="mt-2" />
                        </div>
                        <div class="flex gap-3">
                            <x-danger-button>Delete permanently</x-danger-button>
                            <x-secondary-button type="button" @click="open = false">Cancel</x-secondary-button>
                        </div>
                    </form>
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
