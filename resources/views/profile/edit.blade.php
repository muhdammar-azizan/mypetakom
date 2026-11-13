@php
    $isStudent = $user->isStudent();
    $locked = $isStudent; // name, email and programme come from the faculty record
    $lockedClass = 'bg-slate-100 text-slate-500';
    $orgLabel = $user->isCoordinator() ? 'Department' : 'Programme';
    $roleColor = $user->isCoordinator() ? 'solid' : ($user->isAdvisor() ? 'gold' : 'brand');
@endphp

<x-app-layout title="My Profile">
    <x-slot name="header">My Profile</x-slot>

    <div class="grid gap-5 items-start lg:grid-cols-[1.5fr_1fr]">

        {{-- Left column --}}
        <div class="flex flex-col gap-5 min-w-0">

            {{-- Identity --}}
            <x-card class="p-6">
                <div class="flex flex-wrap items-center gap-5">
                    <div class="relative shrink-0 w-[84px] h-[84px]">
                        @if ($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="Profile photo" class="w-[84px] h-[84px] rounded-full object-cover border border-slate-200">
                        @else
                            <div class="w-[84px] h-[84px] rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-head font-bold text-[26px]">{{ $user->initials }}</div>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="font-head text-[19px] font-bold text-slate-900 mb-1.5">{{ $user->name }}</div>
                        <x-badge :color="$roleColor" class="mb-2">{{ $user->role_label }}</x-badge>
                        <div class="text-[13px] text-slate-500 font-mono">{{ $user->id_label }}: {{ $user->id_value }}</div>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-slate-100 flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <label class="inline-flex items-center min-h-[40px] px-4 rounded-lg border border-slate-300 text-[13px] font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                            Choose photo
                            <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="sr-only" required
                                   onchange="this.form.requestSubmit()">
                        </label>
                    </form>
                    @if ($user->avatar_path)
                        <form method="POST" action="{{ route('profile.avatar.destroy') }}">
                            @csrf @method('DELETE')
                            <button class="min-h-[40px] px-2 text-[13px] font-semibold text-danger-600 hover:underline">Remove photo</button>
                        </form>
                    @endif
                    <span class="text-xs text-slate-400">JPG, PNG or WebP, max 2 MB</span>
                </div>
                <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
            </x-card>

            {{-- Details --}}
            <x-card title="Details">
                <form method="POST" action="{{ route('profile.update') }}" class="flex flex-col gap-4">
                    @csrf @method('PATCH')

                    <div>
                        <x-input-label for="name" class="flex items-center gap-1.5">
                            Full Name @if ($locked) <x-icon name="lock" :size="13" class="text-slate-400" /> @endif
                        </x-input-label>
                        <x-text-input id="name" name="name" type="text" class="block w-full {{ $locked ? $lockedClass : '' }}" :value="old('name', $user->name)" :readonly="$locked" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" class="flex items-center gap-1.5">
                            Email @if ($locked) <x-icon name="lock" :size="13" class="text-slate-400" /> @endif
                        </x-input-label>
                        <x-text-input id="email" name="email" type="email" class="block w-full {{ $locked ? $lockedClass : '' }}" :value="old('email', $user->email)" :readonly="$locked" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="phone" value="Phone Number" />
                        <x-text-input id="phone" name="phone" type="tel" class="block w-full" :value="old('phone', $user->phone)" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="programme" class="flex items-center gap-1.5">
                            {{ $orgLabel }} @if ($locked) <x-icon name="lock" :size="13" class="text-slate-400" /> @endif
                        </x-input-label>
                        <x-text-input id="programme" name="programme" type="text" class="block w-full {{ $locked ? $lockedClass : '' }}" :value="old('programme', $user->programme)" :readonly="$locked" />
                        <x-input-error :messages="$errors->get('programme')" class="mt-2" />
                    </div>

                    @if ($isStudent)
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="emergency_name" value="Emergency Contact Name" />
                                <x-text-input id="emergency_name" name="emergency_name" type="text" class="block w-full" :value="old('emergency_name', $user->emergency_name)" />
                                <x-input-error :messages="$errors->get('emergency_name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="emergency_phone" value="Emergency Contact Number" />
                                <x-text-input id="emergency_phone" name="emergency_phone" type="tel" class="block w-full" :value="old('emergency_phone', $user->emergency_phone)" />
                                <x-input-error :messages="$errors->get('emergency_phone')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="address" value="Home Address" />
                            <textarea id="address" name="address" rows="2" class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-100">{{ old('address', $user->address) }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="alt_email" value="Personal Email" />
                            <x-text-input id="alt_email" name="alt_email" type="email" class="block w-full" placeholder="Optional backup email" :value="old('alt_email', $user->alt_email)" />
                            <x-input-error :messages="$errors->get('alt_email')" class="mt-2" />
                        </div>
                    @endif

                    <div class="flex justify-end mt-1">
                        <x-primary-button>Save Changes</x-primary-button>
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
        </div>

        {{-- Right column --}}
        <div class="flex flex-col gap-5 min-w-0">

            @if ($isStudent)
                <x-card title="My Merit QR" subtitle="Show this to verify your identity and total merit." class="text-center" x-data="{ big: false }">
                    <button type="button" @click="big = true" aria-label="View larger QR code"
                            class="inline-flex w-[160px] h-[160px] border border-slate-200 rounded-xl p-2.5 bg-white hover:shadow-md transition">
                        <span class="block w-full h-full [&>svg]:w-full [&>svg]:h-full">{!! $qrSvg !!}</span>
                    </button>
                    <p class="mt-2 font-mono text-xs text-slate-500">{{ $user->student_id }}</p>

                    <div x-show="big" x-transition.opacity style="display:none"
                         class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4" @click.self="big = false" @keydown.escape.window="big = false">
                        <div class="bg-white rounded-2xl p-6 w-full max-w-sm text-center">
                            <h3 class="font-head font-bold text-slate-900 mb-1">{{ $user->name }}</h3>
                            <p class="text-xs text-slate-500 mb-4 font-mono">{{ $user->student_id }}</p>
                            <div class="mx-auto w-64 h-64 [&>svg]:w-full [&>svg]:h-full">{!! $qrSvg !!}</div>
                            <x-secondary-button class="mt-5" @click="big = false">Close</x-secondary-button>
                        </div>
                    </div>
                </x-card>
            @endif

            <x-card title="Account Info">
                <dl class="flex flex-col gap-3 text-[13.5px]">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Member since</dt><dd class="font-medium text-slate-800">{{ $user->created_at->format('d M Y') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Last login</dt><dd class="font-medium text-slate-800">{{ $user->last_login_at?->diffForHumans() ?? '-' }}</dd></div>
                    <div class="flex justify-between items-center gap-3"><dt class="text-slate-500">Status</dt><dd><x-badge color="green">Active</x-badge></dd></div>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
