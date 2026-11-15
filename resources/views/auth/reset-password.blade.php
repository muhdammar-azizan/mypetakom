<x-guest-layout>
    <h2 class="font-head text-[26px] font-bold text-slate-900 mb-1.5">Choose a new password</h2>
    <p class="text-sm text-slate-500 mb-7">Pick a password you have not used before.</p>

    <form method="POST" action="{{ route('password.store') }}" class="flex flex-col gap-5" x-data="{ show: false }">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $request->email)" required autocomplete="username" class="block w-full bg-slate-100 text-slate-600" readonly />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="New password" />
            <div class="relative">
                <x-icon name="lock" :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                <x-text-input id="password" name="password" ::type="show ? 'text' : 'password'" type="password" required autofocus autocomplete="new-password" class="block w-full pl-10 pr-12" />
                <button type="button" @click="show = ! show" :aria-label="show ? 'Hide password' : 'Show password'"
                        class="absolute right-0.5 top-1/2 -translate-y-1/2 w-11 h-11 flex items-center justify-center text-slate-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-600">
                    <x-icon name="eye" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirm new password" />
            <x-text-input id="password_confirmation" name="password_confirmation" ::type="show ? 'text' : 'password'" type="password" required autocomplete="new-password" class="block w-full" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">Reset password</x-primary-button>
    </form>

    <div class="mt-7 pt-5 border-t border-slate-200 text-[12.5px] text-slate-500 leading-relaxed">
        Link expired? <a href="{{ route('password.request') }}" class="font-semibold text-brand-600">Ask for a new one</a>.
    </div>
</x-guest-layout>
